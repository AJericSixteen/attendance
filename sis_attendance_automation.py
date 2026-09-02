"""
SIS Attendance Automation
-------------------------
Fully dynamic Excel + SIS configuration.

At startup you choose:
- Excel .xlsx/.xlsm file
- Worksheet name
- Student ID column
- Student name column (used only to show the surname in the preview)
- Starting row
- Ending row
- SIS URL

The Excel file only needs to list the student IDs of students who were
PRESENT. The script then goes through EVERY student row on the SIS
"Attendance By Faculty" page:

- If the student's ID is in the Excel list          -> checkbox CHECKED   (Present)
- If the student's ID is NOT in the Excel list       -> checkbox UNCHECKED (Absent)

IMPORTANT:
- You log into SIS manually and open the correct Attendance By Faculty
  page (correct date + subject) before the script starts working.
- The script does NOT click Submit for you. Review the checkboxes in
  SIS and click Submit yourself.
- Keep TEST_MODE=True while testing.
"""

import time
import tkinter as tk
from tkinter import filedialog
from pathlib import Path

import openpyxl
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError


# =========================
# CONFIG
# =========================

# True = pause after every student so you can verify the entry.
# False = continue automatically.
TEST_MODE = False

WAIT_AFTER_UPDATE = 0.3

# In the SIS table, which <td> (0-indexed, counting only <td> cells in
# the row) holds the Student Id text. Based on the Attendance By
# Faculty table layout: column 0 = checkbox, column 1 = Student Id,
# column 2 = Student Name, ... If your SIS table is laid out
# differently, change this number.
ID_COLUMN_INDEX = 1


# =========================
# FILE SELECTION
# =========================

def select_excel_file():
    root = tk.Tk()
    root.withdraw()
    root.attributes("-topmost", True)

    file_path = filedialog.askopenfilename(
        title="Select Excel Attendance File",
        filetypes=[
            ("Excel files", "*.xlsx"),
            ("Excel macro-enabled files", "*.xlsm"),
            ("All files", "*.*"),
        ],
    )

    root.destroy()

    if not file_path:
        return None

    return file_path


# =========================
# INPUT HELPERS
# =========================

def ask_non_empty(prompt):
    while True:
        value = input(prompt).strip()

        if value:
            return value

        print("This field cannot be empty.")


def ask_positive_integer(prompt):
    while True:
        try:
            value = int(input(prompt))

            if value >= 1:
                return value

            print("Please enter a number greater than 0.")

        except ValueError:
            print("Please enter numbers only.")


def ask_column(prompt):
    while True:
        value = input(prompt).strip().upper()

        # Accept A, B, C, AA, AB, etc.
        if value.isalpha():
            return value

        print("Please enter a valid Excel column, such as C, R, AA, etc.")


def ask_sis_url():
    while True:
        url = input("Paste the SIS Attendance By Faculty page URL: ").strip()

        if url.startswith(("http://", "https://")):
            return url

        print("Invalid URL. Please paste the complete SIS URL.")


# =========================
# EXCEL
# =========================

def clean_student_id(value):
    if value is None:
        return ""

    return " ".join(str(value).strip().upper().split())


def get_surname(full_name):
    if not full_name:
        return ""

    text = " ".join(str(full_name).strip().split())

    if "," in text:
        return text.split(",")[0].strip()

    return text


def get_excel_settings(excel_file):
    """
    Ask the user for worksheet, student ID column, and row range.
    Validate everything against the workbook.
    """

    wb = openpyxl.load_workbook(
        excel_file,
        read_only=True,
        data_only=True,
    )

    print()
    print("Available worksheets:")
    for sheet in wb.sheetnames:
        print(f"  - {sheet}")

    print()

    while True:
        sheet_name = ask_non_empty("Enter sheet name: ")

        if sheet_name in wb.sheetnames:
            break

        print(
            f"Sheet '{sheet_name}' was not found."
        )

        print(
            "Please choose one of the worksheets listed above."
        )

    id_column = ask_column(
        "Enter student ID column (e.g. B): "
    )

    name_column = ask_column(
        "Enter student name column (e.g. C): "
    )

    start_row = ask_positive_integer(
        "Enter starting row: "
    )

    end_row = ask_positive_integer(
        "Enter ending row: "
    )

    if end_row < start_row:
        wb.close()
        raise ValueError(
            "Ending row must be greater than or equal to starting row."
        )

    wb.close()

    return (
        sheet_name,
        id_column,
        name_column,
        start_row,
        end_row,
    )


def load_present_ids(
    excel_file,
    sheet_name,
    id_column,
    name_column,
    start_row,
    end_row,
):
    """
    Read the selected range and return the set of student IDs that
    were PRESENT, plus an ordered list (with name) for the preview.
    """

    wb = openpyxl.load_workbook(
        excel_file,
        data_only=True,
    )

    if sheet_name not in wb.sheetnames:
        wb.close()
        raise ValueError(
            f"Sheet '{sheet_name}' was not found."
        )

    ws = wb[sheet_name]

    last_row = min(end_row, ws.max_row)

    ordered_ids = []
    present_ids = set()

    for row in range(start_row, last_row + 1):

        raw_id = ws[
            f"{id_column}{row}"
        ].value

        raw_name = ws[
            f"{name_column}{row}"
        ].value

        student_id = clean_student_id(raw_id)

        # Ignore blank rows.
        if not student_id:
            continue

        ordered_ids.append((row, student_id, get_surname(raw_name)))
        present_ids.add(student_id)

    wb.close()

    return present_ids, ordered_ids


# =========================
# PREVIEW
# =========================

def show_preview(
    excel_file,
    sheet_name,
    id_column,
    name_column,
    start_row,
    end_row,
    sis_url,
    ordered_ids,
):
    print()
    print("=" * 65)
    print("                         SETTINGS")
    print("=" * 65)

    print(f"Excel file     : {excel_file}")
    print(f"Sheet          : {sheet_name}")
    print(f"Student ID col : {id_column}")
    print(f"Name col       : {name_column}")
    print(f"Rows           : {start_row} - {end_row}")
    print(f"SIS URL        : {sis_url}")

    print()
    print("-" * 65)
    print("PREVIEW (students marked PRESENT in Excel)")
    print("-" * 65)

    if not ordered_ids:
        print("No student IDs were found in the selected range.")
        print("-" * 65)
        return

    for row, student_id, surname in ordered_ids[:10]:
        print(f"Row {row:<5} {student_id:<18} {surname:<20} -> PRESENT")

    if len(ordered_ids) > 10:
        print(
            f"... and {len(ordered_ids) - 10} more students"
        )

    print("-" * 65)
    print(f"Students marked PRESENT in Excel: {len(ordered_ids)}")
    print(
        "Every OTHER student found on the SIS roster will be "
        "marked ABSENT (unchecked)."
    )


# =========================
# SIS
# =========================

def find_attendance_frame(page, attempts=6, delay=1.0):
    """
    The attendance table may live inside an <iframe> rather than the
    top-level document. Search the main page and every frame for the
    "Student Id" header, retrying briefly in case the table is still
    loading.
    """

    for attempt in range(attempts):

        for frame in page.frames:

            try:
                header = frame.get_by_text("Student Id", exact=True)

                if header.count() > 0:
                    return frame

            except Exception:
                continue

        if attempt < attempts - 1:
            time.sleep(delay)

    return None


def get_data_rows(frame):
    """
    Locate the attendance table by finding the "Student Id" header,
    then return every row that comes after the header row.
    """

    header = frame.get_by_text("Student Id", exact=True).first

    if header.count() == 0:
        return None

    table = header.locator("xpath=ancestor::table[1]")

    all_rows = table.locator("tr")

    total = all_rows.count()

    data_rows = []
    header_passed = False

    for i in range(total):

        row = all_rows.nth(i)

        if not header_passed:

            if row.get_by_text("Student Id", exact=True).count() > 0:
                header_passed = True

            continue

        data_rows.append(row)

    return data_rows


def get_row_student_id(row):

    cells = row.locator("td")

    if cells.count() <= ID_COLUMN_INDEX:
        return ""

    text = cells.nth(ID_COLUMN_INDEX).inner_text()

    return clean_student_id(text)


def get_row_checkbox(row):

    checkboxes = row.locator('input[type="checkbox"]')

    if checkboxes.count() == 0:
        return None

    return checkboxes.first


# =========================
# MAIN
# =========================

def main():

    print()
    print("=" * 65)
    print("                SIS ATTENDANCE AUTOMATION")
    print("=" * 65)
    print()

    # ---------------------------------
    # Select Excel file
    # ---------------------------------

    print("Select your Excel file...")

    excel_file = select_excel_file()

    if not excel_file:
        print("No file selected. Exiting.")
        return

    print()
    print(f"Selected file: {excel_file}")

    # ---------------------------------
    # Excel settings
    # ---------------------------------

    (
        sheet_name,
        id_column,
        name_column,
        start_row,
        end_row,
    ) = get_excel_settings(excel_file)

    # ---------------------------------
    # SIS URL
    # ---------------------------------

    print()

    sis_url = ask_sis_url()

    # ---------------------------------
    # Load present student IDs
    # ---------------------------------

    print()
    print("Reading Excel file...")

    present_ids, ordered_ids = load_present_ids(
        excel_file=excel_file,
        sheet_name=sheet_name,
        id_column=id_column,
        name_column=name_column,
        start_row=start_row,
        end_row=end_row,
    )

    # ---------------------------------
    # Preview
    # ---------------------------------

    show_preview(
        excel_file=excel_file,
        sheet_name=sheet_name,
        id_column=id_column,
        name_column=name_column,
        start_row=start_row,
        end_row=end_row,
        sis_url=sis_url,
        ordered_ids=ordered_ids,
    )

    if not ordered_ids:
        print()
        print(
            "No student IDs were found. Nothing to do."
        )
        print()
        input("Press ENTER to close...")
        return

    # ---------------------------------
    # Confirmation
    # ---------------------------------

    print()

    confirmation = input(
        "Continue with these settings? (Y/N): "
    ).strip().upper()

    if confirmation != "Y":
        print("Cancelled.")
        return

    # ---------------------------------
    # Open SIS
    # ---------------------------------

    with sync_playwright() as p:

        browser = p.chromium.launch(
            headless=False
        )

        context = browser.new_context()

        page = context.new_page()

        # Handle JS dialogs (alert/confirm/beforeunload) ourselves.
        # Without an explicit handler, Playwright's default auto-dismiss
        # can race with a dialog and crash the driver process.
        def handle_dialog(dialog):
            print(f"  [dialog] {dialog.type}: {dialog.message}")
            dialog.dismiss()

        page.on("dialog", handle_dialog)

        print()
        print("Opening SIS...")

        page.goto(
            sis_url,
            wait_until="domcontentloaded",
        )

        # ---------------------------------
        # Manual SIS login
        # ---------------------------------

        print()
        print("=" * 65)
        print("                         SIS LOGIN")
        print("=" * 65)
        print()
        print("1. Log into SIS manually.")
        print("2. Navigate to Attendance By Faculty.")
        print("3. Select the correct Date and Subject.")
        print("4. Make sure the student attendance checkboxes are visible.")
        print("5. Return to this PowerShell window.")
        print()

        input(
            "Press ENTER when the SIS page is ready..."
        )

        # ---------------------------------
        # Find roster rows
        # ---------------------------------

        print()
        print("Looking for the attendance table (checking iframes too)...")

        attendance_frame = find_attendance_frame(page)

        if attendance_frame is None:
            print(
                "Could not find the attendance table on this page or in "
                "any of its iframes (no 'Student Id' header found)."
            )
            input("Press ENTER to close the browser...")
            browser.close()
            return

        print("Reading student roster from SIS...")

        data_rows = get_data_rows(attendance_frame)

        if not data_rows:
            print(
                "Could not find any student rows below the "
                "'Student Id' header."
            )
            input("Press ENTER to close the browser...")
            browser.close()
            return

        print(f"Found {len(data_rows)} row(s) on the SIS page.")

        # ---------------------------------
        # Process every roster row
        # ---------------------------------

        results = []
        matched_ids = set()

        for index, row in enumerate(data_rows, start=1):

            try:

                student_id = get_row_student_id(row)

                if not student_id:
                    continue

                checkbox = get_row_checkbox(row)

                print()
                print(f"[{index}/{len(data_rows)}] {student_id}")

                if checkbox is None:
                    print(
                        "  Checkbox not found (student may not be "
                        "enlisted for this date)"
                    )

                    results.append(
                        (student_id, "CHECKBOX NOT FOUND")
                    )

                    continue

                present = student_id in present_ids

                if present:
                    matched_ids.add(student_id)

                checkbox.scroll_into_view_if_needed()

                was_checked = checkbox.is_checked()

                checkbox.set_checked(present)

                is_now_checked = checkbox.is_checked()

                print(
                    f"  {'PRESENT (in Excel)' if present else 'ABSENT (not in Excel)'} "
                    f"- was {'CHECKED' if was_checked else 'UNCHECKED'}, "
                    f"now {'CHECKED' if is_now_checked else 'UNCHECKED'}"
                )

                if TEST_MODE:

                    print()
                    print("  TEST MODE:")
                    print("  Verify the checkbox in SIS.")

                    input("  Press ENTER to continue...")

                else:

                    time.sleep(WAIT_AFTER_UPDATE)

                results.append(
                    (
                        student_id,
                        "PRESENT" if present else "ABSENT",
                    )
                )

            except PlaywrightTimeoutError:

                print("  TIMEOUT")

                results.append(
                    (student_id if 'student_id' in locals() else "?", "TIMEOUT")
                )

            except Exception as e:

                print(f"  ERROR: {e}")

                results.append(
                    (student_id if 'student_id' in locals() else "?", f"ERROR: {e}")
                )

        # ---------------------------------
        # Excel IDs never seen on the page
        # ---------------------------------

        unmatched = present_ids - matched_ids

        # ---------------------------------
        # Results
        # ---------------------------------

        print()
        print()
        print("=" * 65)
        print("                         RESULTS")
        print("=" * 65)

        for student_id, status in results:
            print(f"{status:<20} {student_id}")

        if unmatched:
            print()
            print(
                f"WARNING: {len(unmatched)} student ID(s) from Excel were "
                f"NOT found on the SIS roster (possible typo or wrong class):"
            )

            for student_id in sorted(unmatched):
                print(f"  - {student_id}")

        print()
        print(
            "Review the checkboxes in SIS, then click Submit yourself."
        )

        print()

        input(
            "Press ENTER to close the browser..."
        )

        browser.close()


if __name__ == "__main__":
    main()

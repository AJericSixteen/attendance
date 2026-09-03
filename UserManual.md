# Attendance System — User Manual

A plain PHP + MySQL attendance system. Admins create teacher accounts; teachers create subjects and take attendance by scanning student QR codes (or entering them manually), then export attendance to Excel.

This manual covers everything a day-to-day user — Admin or Teacher — needs to know to use the system.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Signing In](#2-signing-in)
3. [Admin Guide](#3-admin-guide)
4. [Teacher Guide](#4-teacher-guide)
5. [Appearance: Dark / Light Mode](#5-appearance-dark--light-mode)
6. [Getting Help: The Help Assistant Chatbot](#6-getting-help-the-help-assistant-chatbot)
7. [Troubleshooting / FAQ](#7-troubleshooting--faq)
8. [Known Limitations](#8-known-limitations)
9. [Quick Reference](#9-quick-reference)

---

## 1. Overview

The system has two roles:

| Role | Can do |
|---|---|
| **Admin** | Create teacher accounts, change a teacher's password, deactivate/reactivate a teacher, permanently delete a teacher account. |
| **Teacher** | Create subjects, take attendance for their own subjects (QR scan or manual entry), export attendance to Excel. |

A teacher only ever sees their **own** subjects and students — never another teacher's. An admin only manages accounts; admins do not take attendance.

---

## 2. Signing In

1. Open the system in your browser (e.g. `http://attendance.test/` or `http://localhost/attendance/`).
2. Enter your **Username or Email** and **Password**.
3. Click the eye-style **Show** link next to the password field if you want to check what you typed before submitting.
4. Click **Sign In**.

You'll be sent to the Admin Dashboard or Teacher Dashboard automatically, depending on your role.

**Notes:**
- The **Remember me** checkbox on the login form is currently cosmetic — it doesn't change how long you stay signed in. Don't rely on it.
- If you see *"Invalid username or password"*, double-check your credentials with your admin.
- If you see *"This account has been deactivated. Contact your administrator,"* your account was turned off by an admin — you cannot log back in until they reactivate it.
- **Teachers cannot reset their own password from the interface.** If you forget it, ask your admin to set a new one for you (see [3.3](#33-managing-a-teacher-account)).
- The default admin account created by `database.sql` is `admin` / `admin123`. **Change this password immediately after first login** if it hasn't been changed already (there's no self-service "change my password" screen yet — update it directly in the `users` table, or ask your developer/IT to do it).
- **Logging out is final.** Once you click Logout, pressing the browser's Back button will not bring you back into your dashboard — you'll be sent to the login page instead.

---

## 3. Admin Guide

### 3.1 Admin Dashboard

After logging in, the Admin Dashboard shows **Teacher Accounts** — a grid of cards, one per teacher, each showing:

- Full name and username
- Email
- Active / Deactivated status badge
- Date the account was created

### 3.2 Creating a Teacher Account

1. Click **+ New Teacher** (top right of the Teacher Accounts page).
2. Fill in:
   - **Full Name**
   - **Username** (used to log in)
   - **Email** (used to log in as an alternative to username)
   - **Password** (minimum 6 characters)
3. Click **Create Teacher**.

The new teacher can now log in immediately with the username/email and password you set.

### 3.3 Managing a Teacher Account

Click any teacher's card to open the **Manage Teacher** window. From there you can:

**Change their password**
1. Enter a **New Password** and **Confirm Password** (minimum 6 characters).
2. Click **Update Password**.

This is the only way a teacher's password can be reset — there is no "forgot password" self-service flow for teachers.

**Deactivate an account**
1. Click **Deactivate Account**.
2. Enter **your own** admin password to confirm.
3. Click **Confirm Deactivation**.

A deactivated teacher can no longer log in, but their subjects and attendance history are preserved.

**Reactivate an account**
- Open a deactivated teacher's card and click **Activate Account**. No password confirmation is required for reactivation.

**Permanently delete an account**
1. On a deactivated teacher's card, a **Danger Zone** section appears with **Delete Account**.
2. Enter your admin password to confirm, then click **Permanently Delete**.

⚠️ **This cannot be undone.** Deleting a teacher also deletes **all of their subjects and every attendance record under those subjects**. Only delete an account if you're certain the data is no longer needed. Consider deactivating instead of deleting if you just want to disable access.

### 3.4 Logging Out

Click **Logout** in the top-right corner of the header at any time.

---

## 4. Teacher Guide

### 4.1 Teacher Dashboard

After logging in, you'll see **My Subjects** — a grid of cards, one per subject you've created, each showing:

- Subject code and name
- Section
- How many students are marked **Present Today**
- Date the subject was created

### 4.2 Creating a Subject

1. Click **+ New Subject**.
2. Fill in:
   - **Subject Code** (e.g. `IT101`)
   - **Subject Name** (e.g. `Introduction to Programming`)
   - **Section** (e.g. `BSIT-3A`)
3. Click **Save & Continue**.

Made a typo, or need to retire a subject later? See [4.3](#43-managing-a-subject) — subjects can be edited or deactivated/deleted at any time.

### 4.3 Managing a Subject

Click the **gear icon** in the top-right corner of a subject card (not the card itself — that opens the attendance scanner instead) to open **Manage Subject**.

**Edit a subject**
1. Update the **Subject Code**, **Subject Name**, and/or **Section**.
2. Click **Save Changes**.

**Deactivate a subject**
1. Click **Deactivate Subject** and confirm.
2. The subject now shows a **Deactivated** badge and moves below your active subjects on the dashboard.
3. While deactivated, you **cannot record new attendance** for it (the QR scanner and manual entry are disabled) — but you can still open it, view its full attendance history, and export it to Excel.

**Reactivate a subject**
- Open a deactivated subject's Manage panel and click **Activate Subject** to resume taking attendance for it.

**Delete a subject**
- Only available **after a subject has been deactivated** — an active subject must be deactivated first.
- Open the deactivated subject's Manage panel, scroll to the **Danger Zone**, and click **Delete Subject**, then confirm.
- This removes the subject from your dashboard, but **does not erase its data** — every attendance record already collected for it is kept in the system. There's currently no way to view a deleted subject's history from the interface again, so export anything you still need *before* deleting.

### 4.4 Taking Attendance — QR Scanner

1. Click a subject card to open its **Attendance** window.
2. Click the camera icon (top-left of the camera panel) to turn the camera on.
3. Your browser will ask for camera permission the first time — click **Allow**.
4. Point a student's QR code at the camera. Detection is automatic and continuous — you don't need to press anything per student.
5. Each successful scan appears in the **Present Today** list on the right, along with a green confirmation message (e.g. *"SERRANO marked present."*).

**How it works under the hood** (useful for troubleshooting):
- Each student's QR code encodes their **student number and surname**, in the format `studentnumber_SURNAME` (e.g. `06-2526-001648_SERRANO`).
- If a scanned code isn't in that format, you'll see *"Unrecognized QR code format"* — it's likely not a code generated for this system.
- A student can only be marked present **once per subject per calendar day**. Scanning them again just shows *"<Surname> is already marked present today"* — it will not create a duplicate.
- There's a short (~3 second) cooldown before the exact same code can be scanned again, to prevent accidental double scans while the camera is still pointed at one student.
- Closing the Attendance window automatically turns the camera off.
- If the subject has been **deactivated** (see [4.3](#43-managing-a-subject)), the camera and manual entry are disabled and a notice explains why — reactivate the subject to resume taking attendance.

### 4.5 Taking Attendance — Manual Entry

Use this when a student doesn't have (or can't show) their QR code, or the camera isn't available:

1. Inside the Attendance window, click **+ Manual Entry**.
2. Enter the **Student Number** — it auto-formats as you type into `NN-NNNN-NNNNNN` (e.g. `06-2526-001648`).
3. Enter the **Surname** — it's automatically uppercased.
4. Click **Add**.

The same once-per-subject-per-day rule applies to manual entries.

### 4.6 Correcting a Mistake

There is currently **no button to edit or delete** an individual attendance record once it's been saved (whether scanned or entered manually) — this is different from deleting a whole *subject* (see [4.3](#43-managing-a-subject)). If you make a mistake on a single record, contact your admin or developer for a manual correction — there's no self-service fix in the interface today.

### 4.7 Exporting Attendance to Excel

1. Inside a subject's Attendance window, find **Export date** in the bottom toolbar.
   - Leave it **blank** to export the subject's **entire** attendance history.
   - Pick a specific date to export **only that day's** records.
2. Click **Export to Excel**.
3. An `.xlsx` file downloads:
   - All-time export: `<SUBJECT_CODE>_attendance.xlsx`
   - Date-filtered export: `<SUBJECT_CODE>_attendance_<YYYY-MM-DD>.xlsx`
4. The file lists **No.**, **Student Number**, **Surname**, and **Timestamp** for the matching records.

This works the same way for deactivated subjects, so you can still pull historical records after deactivating one.

> **Optional / advanced:** some schools use a separate desktop script (`sis_attendance_automation.py`) to transfer that exported Excel list into the school's SIS "Attendance By Faculty" page automatically. It checks the box for students who were present and leaves the rest unchecked, but it does **not** submit for you — you still review and click Submit yourself in SIS. This tool lives outside the web app; ask your admin/IT if your school uses it.

---

## 5. Appearance: Dark / Light Mode

Click the moon/sun icon in the top-right corner of any page to toggle between dark and light mode. Your choice is remembered on that browser/device (it won't follow you to a different browser or computer).

---

## 6. Getting Help: The Help Assistant Chatbot

Every page has a floating chat bubble in the bottom-right corner. Click it to open the **Help Assistant** — an AI chatbot that knows how this system works and can walk you through:

- Taking attendance (QR scan or manual entry)
- Camera/permission problems
- Exporting to Excel
- Creating subjects
- What to do if you're locked out or forgot your password

Type your question and press Enter or click the send button. Click the **×** to close the panel.

The assistant will **never** tell you anyone's password — for that, always contact your admin.

*(If the chat bubble shows "not configured yet," it means the site owner hasn't set up the Dify API key — this doesn't affect anything else in the system.)*

---

## 7. Troubleshooting / FAQ

**The camera won't turn on / shows "Camera access is required."**
Your browser blocked camera access. On desktop Chrome/Edge, click the padlock/site-info icon in the address bar → set **Camera** to **Allow** → reload the page. On a phone, allow camera access for your browser app in the phone's system Settings, then reload.

**The QR code won't scan.**
Make sure there's good lighting and the code isn't blurry, too small, or too far from the camera. Confirm it's a code actually generated for this system (format: `studentnumber_SURNAME`). If it still won't scan, use **Manual Entry** instead.

**A student is shown as "already marked present" but I don't think I scanned them.**
Each student can only be marked present once per subject per day — this message means a record already exists for today. If it's genuinely wrong, there's no self-service correction yet; contact your admin.

**I forgot my password (teacher).**
Ask your admin to reset it from the Admin Dashboard (see [3.3](#33-managing-a-teacher-account)). There's no self-service "forgot password" link.

**I can't log in / it says my account is deactivated.**
Your admin turned off your account. Contact them to reactivate it.

**How do I edit or delete a subject?**
Click the gear icon on the subject's card. You can edit its details anytime; deleting requires deactivating it first. See [4.3](#43-managing-a-subject).

**I deactivated a subject — where did its attendance data go?**
Nowhere — it's still there. Deactivating (and even deleting) a subject only hides it from your dashboard and blocks *new* attendance; every record already collected stays in the system and can still be exported until the subject is deleted. Once deleted, there's no interface to view its history again, so export what you need first.

**Why can't I scan or manually add attendance for a subject anymore?**
It's likely been deactivated. Open its Manage panel (gear icon) and click **Activate Subject** to resume.

**How do I delete a single attendance record?**
Not supported in the interface today — this is different from deleting a whole subject. Contact your admin/developer for a manual correction.

**Where does the exported Excel file go?**
It downloads through your browser like any other file — check your Downloads folder (or wherever your browser saves downloads).

---

## 8. Known Limitations

These aren't bugs — they're simply not built yet, so it's worth knowing about them:

- No self-service password reset for teachers.
- No editing or deleting individual attendance records (deleting a whole *subject* is supported — see [4.3](#43-managing-a-subject)).
- Once a subject is deleted, there's no interface to view its preserved attendance history again — export first.
- The "Remember me" checkbox on login has no effect.
- Admin cannot change their own password from the interface (must be updated directly in the database).

---

## 9. Quick Reference

| Item | Format / Value |
|---|---|
| QR code content | `studentnumber_SURNAME` |
| Student number format | `NN-NNNN-NNNNNN` (e.g. `06-2526-001648`) |
| Minimum password length | 6 characters |
| Attendance rule | One scan/entry per student, per subject, per calendar day |
| Exported file name (all-time) | `<SUBJECT_CODE>_attendance.xlsx` |
| Exported file name (date-filtered) | `<SUBJECT_CODE>_attendance_<YYYY-MM-DD>.xlsx` |
| Subject deletion | Soft delete — hidden from dashboard, attendance records preserved |
| Default admin login (change immediately) | `admin` / `admin123` |

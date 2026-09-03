<?php

require __DIR__ . '/../includes/env.php';
load_env(__DIR__ . '/../.env');

// Dify Cloud app settings. Create an API key under your Dify app -> API
// Access, then set DIFY_API_KEY (and DIFY_API_BASE_URL, if self-hosted) in .env
$difyApiKey = env('DIFY_API_KEY', '');
$difyApiBaseUrl = env('DIFY_API_BASE_URL', 'https://api.dify.ai/v1');

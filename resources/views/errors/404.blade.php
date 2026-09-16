<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Not Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center px-4">
    <div class="display-1 fw-bold text-secondary mb-3">404</div>
    <h2 class="mb-2">Page Not Found</h2>
    <p class="text-muted mb-4">The page or record you are looking for does not exist.</p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
</div>
</body>
</html>

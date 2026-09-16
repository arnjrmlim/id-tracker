<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Forbidden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center px-4">
    <div class="display-1 fw-bold text-danger mb-3">403</div>
    <h2 class="mb-2">Access Denied</h2>
    <p class="text-muted mb-4">{{ $message ?? 'You do not have permission to perform this action.' }}</p>
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary me-2">Go Back</a>
    <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
</div>
</body>
</html>

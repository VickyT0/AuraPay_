<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AuraPay — Register</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 420px; margin: 80px auto; padding: 0 20px; }
        input { display: block; width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { padding: 8px 16px; margin-top: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Create your AuraPay account</h1>

    <form method="POST" action="/register">
        @csrf
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="password_confirmation" placeholder="Confirm password" required>
        <button type="submit">Register</button>
    </form>

    @if ($errors->any())
        <ul style="color:red">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <p><a href="/login">Already have an account?</a></p>
</body>
</html>

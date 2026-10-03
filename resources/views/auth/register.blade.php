<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans antialiased flex items-center justify-center min-h-screen py-12">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl mb-4 text-2xl">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Create an Account</h2>
            <p class="text-xs text-gray-500 mt-1">Create a member account</p>
        </div>

        <!-- Alert: Store route not yet implemented -->
        @if(session('info'))
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
            {{ session('info') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="John Doe"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-300 @enderror"
                    required>
                @error('name')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="john@example.com"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-300 @enderror"
                    required>
                @error('email')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-300 @enderror"
                    required>
                @error('password')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-300 @enderror"
                    required>
                @error('password')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm mt-2">
                Register Account
            </button>
        </form>

        <div class="text-center mt-6">
            <p class="text-xs text-gray-500">Already have an account? <a href="{{ route('login') }}" class="text-indigo-600 font-semibold hover:underline">Sign in</a></p>
        </div>
    </div>
</body>
</html>

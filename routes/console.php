<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('panel:admin {email}', function (string $email) {
    if (Validator::make(['email' => $email], ['email' => 'required|email|max:255|unique:users,email'])->fails()) { $this->error('Invalid or existing email.'); return 1; }
    $password = $this->secret('Password (at least 16 characters)');
    if (! is_string($password) || strlen($password) < 16 || $password !== $this->secret('Confirm password')) { $this->error('Passwords do not match or are too short.'); return 1; }
    User::create(['name' => 'Administrator', 'email' => $email, 'password' => Hash::make($password)]);
    $this->info('Administrator created. All panel users have full server management access.');
    return 0;
})->purpose('Create a panel administrator');

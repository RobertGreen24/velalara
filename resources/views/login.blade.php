@extends('layout')
@section('content')
<section style="max-width:420px;margin:4rem auto"><h1>Administrator sign in</h1><p class="muted">Manage this server securely.</p><form method="post" action="{{ route('login') }}">@csrf<p><label>Email<br><input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></label></p><p><label>Password<br><input type="password" name="password" required autocomplete="current-password"></label></p><button>Sign in</button></form></section>
@endsection

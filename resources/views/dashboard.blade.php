@extends('layout')
@section('content')
<h1>Hosting dashboard</h1><p class="muted">All signed-in users are server administrators.</p>
@if(session('credentials'))<section class="notice"><h2>Database credentials — shown once</h2><p>Save these securely. The password is not stored in the panel database.</p><pre>Database: {{ session('credentials.database') }}
User: {{ session('credentials.db_user') }}
Password: {{ session('credentials.password') }}
Host: localhost</pre></section>@endif
<section><h2>Create hosting account</h2><form class="inline" method="post" action="{{ route('accounts.create') }}">@csrf<input name="label" placeholder="Account label" maxlength="100" required><button>Create account</button></form><p class="muted">A locked, non-interactive Linux user is generated automatically.</p></section>
@forelse($accounts as $account)
<section><h2>{{ $account->label }}</h2><p><code>{{ $account->username }}</code> · {{ $account->status }}</p>
@if($account->status === 'active')
<p><a href="{{ route('files.index', $account) }}">Open file manager →</a></p>
<h3>Domains</h3><form class="inline" method="post" action="{{ route('domains.create', $account) }}">@csrf<input name="domain" placeholder="example.org" required><button>Add domain</button></form>
@foreach($account->domains as $domain)<div class="card"><strong>{{ $domain->name }}</strong> · {{ $domain->status }} · {{ $domain->ssl ? 'TLS enabled' : 'HTTP only' }}
@if($domain->status === 'active')<form class="inline" method="post" action="{{ route('certificates.create', $domain) }}">@csrf<input type="email" name="email" placeholder="Certificate contact email" required><button>{{ $domain->ssl ? 'Recheck certificate' : 'Issue TLS certificate' }}</button></form>@endif</div>@endforeach
<h3>Databases</h3><form method="post" action="{{ route('databases.create', $account) }}">@csrf<button>Create database and user</button></form>
<ul>@foreach($account->databases as $db)<li><code>{{ $db->name }}</code> · user <code>{{ $db->db_user }}</code></li>@endforeach</ul>
@else<p class="muted">Provisioning did not complete. An administrator must reconcile resources on the server.</p>@endif
</section>
@empty<section><p>No accounts yet.</p></section>@endforelse
@endsection

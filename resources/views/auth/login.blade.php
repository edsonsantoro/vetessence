@extends('layouts.auth', ['title' => 'Login'])

@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    
    <div class="input-group mb-3">
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
               placeholder="Email" value="{{ old('email') }}" required autofocus>
        <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
        </div>
    </div>
    
    <div class="input-group mb-3">
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
               placeholder="Senha" required autocomplete="current-password">
        <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-8">
            <div class="icheck-primary">
                <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">Lembrar-me</label>
            </div>
        </div>
        <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </div>
    </div>

    <div class="text-center mt-3">
        @unless(config('demo.enabled'))
        <a href="{{ route('password.request') }}" class="text-sm">Esqueceu a senha?</a>
        @endunless
    </div>
</form>
@endsection

@push('after_login_card')
    @if(config('demo.enabled'))
    <style>
        /* Em modo demo a tela de login fica mais larga para a tabela de contas
           caber sem scroll horizontal. */
        .login-box { width: 100% !important; max-width: 600px; }
        @media (max-width: 576px) { .login-box { width: 92% !important; } }
        .demo-accounts .card-header { padding: .4rem .75rem; }
        .demo-accounts .card-header h3 { font-size: .95rem; margin: 0; }
        .demo-accounts .card-body { padding: .5rem .6rem !important; }
        .demo-accounts table { font-size: .78rem; margin-bottom: 0; }
        .demo-accounts table th,
        .demo-accounts table td { padding: .25rem .45rem; white-space: nowrap; }
        .demo-accounts table code { font-size: .76rem; }
        .demo-accounts .demo-note { font-size: .72rem; margin-top: .4rem; }
    </style>
    <div class="mt-3 demo-accounts">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-circle mr-1"></i> Contas de demonstração</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Perfil</th>
                            <th>Email</th>
                            <th>Senha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(config('demo.accounts') as $account)
                        <tr>
                            <td>{{ $account['profile'] }}</td>
                            <td><code>{{ $account['email'] }}</code></td>
                            <td><code>{{ $account['password'] }}</code></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-muted demo-note mb-0">
                    Ambiente de demonstração: as senhas destas contas não podem ser alteradas.
                </p>
            </div>
        </div>
    </div>
    @endif
@endpush

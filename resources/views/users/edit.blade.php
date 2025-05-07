@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-lg-12">
            <h2>Editar Usuário</h2>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ops!</strong> Houve alguns problemas com sua entrada.<br><br>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route("users.update", $user->id) }}" method="POST">
        @csrf
        @method("PUT")

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Nome:</strong>
                    <input type="text" name="name" value="{{ $user->name }}" class="form-control" placeholder="Nome do Usuário">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Email:</strong>
                    <input type="email" name="email" value="{{ $user->email }}" class="form-control" placeholder="Email">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Nova Senha (deixe em branco para não alterar):</strong>
                    <input type="password" name="password" class="form-control" placeholder="Nova Senha">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Confirmar Nova Senha:</strong>
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Confirmar Nova Senha">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>ID VExpenses (Opcional):</strong>
                    <input type="text" name="vexpenses_id" value="{{ $user->vexpenses_id }}" class="form-control" placeholder="ID do Usuário no VExpenses">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12 text-center">
                <button type="submit" class="btn btn-primary">Atualizar</button>
                <a class="btn btn-secondary" href="{{ route("users.index") }}"> Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection


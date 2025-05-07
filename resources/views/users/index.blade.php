@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Usuários</h2>
        </div>
        <div class="col-md-6 text-right">
            <a href="{{ route("users.create") }}" class="btn btn-primary">Adicionar Novo Usuário</a>
        </div>
    </div>

    @if ($message = Session::get("success"))
        <div class="alert alert-success">
            <p>{{ $message }}</p>
        </div>
    @endif

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>ID VExpenses</th>
                <th width="280px">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td><a href="{{ route('users.show', $user->id) }}">{{ $user->name }}</a></td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->vexpenses_id ?? 'N/A' }}</td>
                    <td>
                        <form action="{{ route("users.destroy", $user->id) }}" method="POST">
                            <a class="btn btn-info btn-sm" href="{{ route("users.show", $user->id) }}">Ver</a>
                            <a class="btn btn-primary btn-sm" href="{{ route("users.edit", $user->id) }}">Editar</a>
                            @csrf
                            @method("DELETE")
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">Excluir</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Nenhum usuário encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {!! $users->links() !!}
</div>
@endsection


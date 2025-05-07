@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Centros de Custo</h2>
        </div>
        <div class="col-md-6 text-right">
            <a href="{{ route("cost_centers.create") }}" class="btn btn-primary">Adicionar Novo Centro de Custo</a>
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
                <th>Código</th>
                <th>Descrição</th>
                <th width="280px">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($costCenters as $costCenter)
                <tr>
                    <td>{{ $costCenter->id }}</td>
                    <td>{{ $costCenter->name }}</td>
                    <td>{{ $costCenter->code }}</td>
                    <td>{{ Str::limit($costCenter->description, 50) }}</td>
                    <td>
                        <form action="{{ route("cost_centers.destroy", $costCenter->id) }}" method="POST">
                            <a class="btn btn-info btn-sm" href="{{ route("cost_centers.show", $costCenter->id) }}">Ver</a>
                            <a class="btn btn-primary btn-sm" href="{{ route("cost_centers.edit", $costCenter->id) }}">Editar</a>
                            @csrf
                            @method("DELETE")
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm("Tem certeza que deseja excluir este centro de custo?")">Excluir</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Nenhum centro de custo encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {!! $costCenters->links() !!}
</div>
@endsection


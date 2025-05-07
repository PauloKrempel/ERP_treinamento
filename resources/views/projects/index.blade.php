@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Projetos</h2>
        </div>
        <div class="col-md-6 text-right">
            <a href="{{ route("projects.create") }}" class="btn btn-primary">Adicionar Novo Projeto</a>
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
                <th>Data Início</th>
                <th>Data Fim</th>
                <th width="280px">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $project)
                <tr>
                    <td>{{ $project->id }}</td>
                    <td>{{ $project->name }}</td>
                    <td>{{ $project->code }}</td>
                    <td>{{ Str::limit($project->description, 50) }}</td>
                    <td>{{ $project->start_date ? 
                        
                        Carbon\Carbon::parse($project->start_date)->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $project->end_date ? Carbon\Carbon::parse($project->end_date)->format('d/m/Y') : 'N/A' }}</td>
                    <td>
                        <form action="{{ route("projects.destroy", $project->id) }}" method="POST">
                            <a class="btn btn-info btn-sm" href="{{ route("projects.show", $project->id) }}">Ver</a>
                            <a class="btn btn-primary btn-sm" href="{{ route("projects.edit", $project->id) }}">Editar</a>
                            @csrf
                            @method("DELETE")
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm("Tem certeza que deseja excluir este projeto?")">Excluir</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Nenhum projeto encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {!! $projects->links() !!}
</div>
@endsection


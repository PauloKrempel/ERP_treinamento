@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-lg-12">
            <h2>Editar Projeto</h2>
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

    <form action="{{ route("projects.update", $project->id) }}" method="POST">
        @csrf
        @method("PUT")

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Nome:</strong>
                    <input type="text" name="name" value="{{ $project->name }}" class="form-control" placeholder="Nome do Projeto">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Código:</strong>
                    <input type="text" name="code" value="{{ $project->code }}" class="form-control" placeholder="Código (para VExpenses)">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Descrição:</strong>
                    <textarea class="form-control" style="height:150px" name="description" placeholder="Descrição">{{ $project->description }}</textarea>
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Data de Início:</strong>
                    <input type="date" name="start_date" value="{{ $project->start_date }}" class="form-control">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Data de Fim:</strong>
                    <input type="date" name="end_date" value="{{ $project->end_date }}" class="form-control">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12 text-center">
                <button type="submit" class="btn btn-primary">Atualizar</button>
                <a class="btn btn-secondary" href="{{ route("projects.index") }}"> Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection


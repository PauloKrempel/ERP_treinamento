@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-lg-12">
            <h2>Detalhes do Projeto</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Nome:</strong>
                {{ $project->name }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Código:</strong>
                {{ $project->code }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Descrição:</strong>
                {{ $project->description ?? 'N/A' }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-6">
            <div class="form-group">
                <strong>Data de Início:</strong>
                {{ $project->start_date ? Carbon\Carbon::parse($project->start_date)->format('d/m/Y') : 'N/A' }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-6">
            <div class="form-group">
                <strong>Data de Fim:</strong>
                {{ $project->end_date ? Carbon\Carbon::parse($project->end_date)->format('d/m/Y') : 'N/A' }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Criado em:</strong>
                {{ $project->created_at->format('d/m/Y H:i:s') }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Atualizado em:</strong>
                {{ $project->updated_at->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <a class="btn btn-primary" href="{{ route('projects.index') }}"> Voltar</a>
        </div>
    </div>
</div>
@endsection


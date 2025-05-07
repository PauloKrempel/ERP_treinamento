@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-lg-12">
            <h2>Detalhes do Centro de Custo</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Nome:</strong>
                {{ $costCenter->name }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Código:</strong>
                {{ $costCenter->code }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Descrição:</strong>
                {{ $costCenter->description ?? 'N/A' }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Criado em:</strong>
                {{ $costCenter->created_at->format('d/m/Y H:i:s') }}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Atualizado em:</strong>
                {{ $costCenter->updated_at->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <a class="btn btn-primary" href="{{ route('cost_centers.index') }}"> Voltar</a>
        </div>
    </div>
</div>
@endsection


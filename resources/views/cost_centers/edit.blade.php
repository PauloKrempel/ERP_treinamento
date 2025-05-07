@extends("layouts.app")

@section("content")
<div class="container">
    <div class="row mb-3">
        <div class="col-lg-12">
            <h2>Editar Centro de Custo</h2>
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

    <form action="{{ route("cost_centers.update", $costCenter->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Nome:</strong>
                    <input type="text" name="name" value="{{ $costCenter->name }}" class="form-control" placeholder="Nome do Centro de Custo">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Código:</strong>
                    <input type="text" name="code" value="{{ $costCenter->code }}" class="form-control" placeholder="Código (para VExpenses)">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12">
                <div class="form-group">
                    <strong>Descrição:</strong>
                    <textarea class="form-control" style="height:150px" name="description" placeholder="Descrição">{{ $costCenter->description }}</textarea>
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-12 text-center">
                <button type="submit" class="btn btn-primary">Atualizar</button>
                <a class="btn btn-secondary" href="{{ route("cost_centers.index") }}"> Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection


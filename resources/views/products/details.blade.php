 @extends('layouts.master')

@section('content')
@php
  use Illuminate\Support\Str;
  $currency = config('app.currency_symbol', '$');
  $productName = $product->name ?? 'Product';
@endphp

<section class="border-bottom bg-light">
  <div class="container py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <a href="/app/products" class="text-decoration-none">
          <i class="bi bi-arrow-left"></i> Back to Products
        </a>
        <h1 class="h5 mb-0 mt-2">{{ $productName }} <small class="small-muted">(ID: {{ $product->id ?? '-' }})</small></h1>
        <div class="small text-muted mt-1">Orders & Purchases for this product</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/app/products">Products</a>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-11">

      <div class="row">
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
              <h5 class="mb-3">Saless</h5>
              @if(!empty($sales) && count($sales) > 0)
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Date</th>
                        <th>Ref</th>
                        <th>Sale</th>
                        <th>Client</th>
                        <th>Warehouse</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($sales as $s)
                        <tr>
                          <td>{{ $s['date'] ?? '-' }}</td>
                          <td>{{ $s['Ref'] ?? '-' }}</td>
                          <td>{{ $s['sale_id'] ?? '-' }}</td>
                          <td>{{ $s['client_name'] ?? '-' }}</td>
                          <td>{{ $s['warehouse_name'] ?? '-' }}</td>
                          <td>{{ $s['batch_no'] ?? '-' }}</td>
                          <td>{{ $s['expiry_date'] ? \Carbon\Carbon::parse($s['expiry_date'])->format('Y-m-d') : '-' }}</td>
                          <td class="text-end">{{ $s['quantity'] ?? '-' }}</td>
                          <td class="text-end">{{ number_format((float) ($s['total'] ?? 0), 2, '.', ',') }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="text-muted">No sales found for this product.</div>
              @endif
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
              <h5 class="mb-3">Purchases</h5>
              @if(!empty($purchases) && count($purchases) > 0)
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Date</th>
                        <th>Ref</th>
                        <th>Purchase</th>
                        <th>Provider</th>
                        <th>Warehouse</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($purchases as $p)
                        <tr>
                          <td>{{ $p['date'] ?? '-' }}</td>
                          <td>{{ $p['Ref'] ?? '-' }}</td>
                          <td>{{ $p['purchase_id'] ?? '-' }}</td>
                          <td>{{ $p['provider_name'] ?? '-' }}</td>
                          <td>{{ $p['warehouse_name'] ?? '-' }}</td>
                          <td>{{ $p['batch_no'] ?? '-' }}</td>
                          <td>{{ $p['expiry_date'] ? \Carbon\Carbon::parse($p['expiry_date'])->format('Y-m-d') : '-' }}</td>
                          <td class="text-end">{{ $p['quantity'] ?? '-' }}</td>
                          <td class="text-end">{{ number_format((float) ($p['total'] ?? 0), 2, '.', ',') }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="text-muted">No purchases found for this product.</div>
              @endif
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

@endsection
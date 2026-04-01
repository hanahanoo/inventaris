@if(request('q'))
  <div class="alert alert-info border-0 shadow-sm py-2 mb-4 animate__animated animate__fadeIn">
    <i class="bi bi-search me-2"></i> Hasil pencarian untuk:
    <strong class="text-dark">{{ request('q') }}</strong>
  </div>
@endif
<div class="row gy-4">
  @forelse ($items as $item)
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
      <div class="card product-card position-relative">
        <div class="position-relative">
          <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->name }}">
          @if ($item->stock == 0)
            <span class="badge-status bg-danger text-white">Habis</span>
          @elseif ($item->stock < 5)
            <span class="badge-status bg-warning text-dark">Menipis</span>
          @endif
        </div>

        <div class="card-body d-flex flex-column justify-content-between">
          <h5 class="fw-semibold mb-2 text-truncate">{{ $item->name }}</h5>
          <p class="small mb-1"><i class="bi bi-tag me-1"></i> Kategori:
            <span class="fw-semibold text-dark">{{ $item->category->name ?? '-' }}</span>
          </p>
          <p class="small mb-3"><i class="bi bi-box me-1"></i> Stok:
            <span class="fw-semibold {{ $item->stock == 0 ? 'text-danger' : 'text-success' }}">{{ $item->stock }}</span>
          </p>

          <form action="{{ route('pegawai.permintaan.create') }}" method="POST" class="mt-auto">
            @csrf
            <input type="hidden" name="items[0][item_id]" value="{{ $item->id }}">
            <div class="d-flex align-items-center justify-content-between">
              <div class="input-group" style="max-width: 110px;">
                <input type="number" name="items[0][quantity]" class="form-control text-center border-warning"
                  value="1" min="1" {{ $item->stock == 0 ? 'disabled' : '' }}>
              </div>
              <button type="submit"
                class="btn btn-sm btn-primary ms-2 d-flex align-items-center shadow-sm"
                {{ $item->stock == 0 ? 'disabled' : '' }}>
                <i class="bi bi-cart-plus me-1"></i> Ajukan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @empty
    <div class="col-12 text-center py-5">
      <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
      <p class="text-muted mb-0">Tidak ada produk ditemukan.</p>
    </div>
  @endforelse
</div>
@if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
  {{-- PAGINATION --}}
  <div class="mt-4 d-flex justify-content-center">
    {{ $items->links('pagination::bootstrap-5') }}
  </div>
</div>
@endif
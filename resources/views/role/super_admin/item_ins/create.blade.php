@extends('layouts.index')

@section('content')
<div class="container-fluid py-4 animate__animated animate__fadeIn">

  {{-- ======================== --}}
  {{-- 🧭 BREADCRUMB ORANGE --}}
  {{-- ======================== --}}
  <div class="bg-white shadow-sm rounded-4 px-4 py-3 mb-4 d-flex flex-wrap align-items-center justify-content-between smooth-fade">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <i class="bi bi-box-arrow-in-down fs-5" style="color:#FF9800;"></i>
      <a href="{{ route('super_admin.dashboard') }}" class="breadcrumb-link fw-semibold text-decoration-none" style="color:#FF9800;">
        Dashboard
      </a>
      <span class="text-muted">/</span>
      <a href="{{ route('super_admin.item_ins.index') }}" class="fw-semibold text-decoration-none" style="color:#FFB300;">
        Barang Masuk
      </a>
      <span class="text-muted">/</span>
      <span class="fw-semibold text-dark">Tambah</span>
    </div>
  </div>

  {{-- ======================== --}}
  {{-- 📦 FORM TAMBAH BARANG MASUK --}}
  {{-- ======================== --}}
  <div class="card border-0 shadow-sm rounded-4 smooth-fade">
    <div class="card-header bg-white border-0 d-flex justify-content-between flex-wrap align-items-center">
      <h4 class="fw-bold mb-0" style="color:#FF9800;">
        <i class="ri-add-line me-2"></i> Tambah Barang Masuk
      </h4>
      <small class="text-warning fw-semibold">Isi data barang masuk dengan benar</small>
    </div>

    <div class="card-body bg-white p-4 rounded-bottom-4">
      <form action="{{ route('super_admin.item_ins.store') }}" method="POST" x-data="multipleInput()">
        @csrf

        {{-- Container untuk multiple rows --}}
        <div id="items-container">
          <template x-for="(row, index) in rows" :key="row.id">
            <div class="row g-3 align-items-end mb-3 item-row" :class="{'pb-3 border-bottom': index < rows.length - 1}">
              
              {{-- Item --}}
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark mb-2" x-show="index === 0">Barang</label>
                <select :name="'items[' + index + '][item_id]'" 
                        class="form-select shadow-sm border-0 select2-dynamic"
                        style="border-left:4px solid #FF9800 !important;" 
                        required
                        x-model="row.selectedItem"
                        @change="validateSupplier(index)">
                  <option value="">-- Pilih Barang --</option>
                  @foreach($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                  @endforeach
                </select>
                <small class="text-danger" x-show="row.itemError" x-text="row.itemError"></small>
              </div>

              {{-- Supplier --}}
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark mb-2" x-show="index === 0">Supplier</label>
                <select :name="'items[' + index + '][supplier_id]'" 
                        class="form-select shadow-sm border-0 select2-dynamic"
                        :style="row.supplierError ? 'border-left:4px solid #dc3545 !important;' : 'border-left:4px solid #FF9800 !important;'"
                        required
                        x-model="row.selectedSupplier"
                        @change="validateSupplier(index)">
                  <option value="">-- Pilih Supplier --</option>
                  @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                  @endforeach
                </select>
                <small class="text-danger" x-show="row.supplierError" x-text="row.supplierError"></small>
              </div>

              {{-- Jumlah --}}
              <div class="col-md-2">
                <label class="form-label fw-semibold text-dark mb-2" x-show="index === 0">Jumlah</label>
                <input type="number" :name="'items[' + index + '][quantity]'" class="form-control shadow-sm border-0"
                       placeholder="Jumlah" required min="1"
                       style="border-left:4px solid #FF9800 !important;">
              </div>

              {{-- Tanggal Expired --}}
              <div class="col-md-2">
                <label class="form-label fw-semibold text-dark mb-2" x-show="index === 0">Tgl Kedaluwarsa</label>
                <div x-show="row.useExpired" x-transition>
                  <input type="date" :name="'items[' + index + '][expired_at]'" 
                         min="{{ \Carbon\Carbon::today()->toDateString() }}"
                         class="form-control shadow-sm border-0"
                         style="border-left:4px solid #FF9800 !important;"
                         :required="row.useExpired">
                </div>
                <div x-show="!row.useExpired" x-transition>
                  <input type="text" class="form-control shadow-sm border-0" value="Tidak digunakan" disabled
                         style="border-left:4px solid #ccc !important;">
                </div>
              </div>

              {{-- Toggle & Tombol --}}
              <div class="col-md-2">
                <div class="d-flex gap-2 align-items-center">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" :id="'toggleExpired' + index" 
                           x-model="row.useExpired">
                    <label class="form-check-label text-muted small" :for="'toggleExpired' + index">
                      Expired
                    </label>
                  </div>
                  
                  {{-- Tombol Hapus (hanya muncul jika lebih dari 1 row) --}}
                  <button type="button" 
                          x-show="rows.length > 1"
                          @click="removeRow(index)"
                          class="btn btn-sm btn-danger rounded-circle"
                          style="width: 32px; height: 32px; padding: 0;">
                    <i class="ri-close-line"></i>
                  </button>
                </div>
              </div>

            </div>
          </template>
        </div>

        {{-- Tombol Tambah Baris --}}
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
          <button type="button" 
                  @click="addRow()"
                  class="btn btn-sm rounded-pill px-4"
                  style="background-color:#E3F2FD;color:#2196F3;border:1px solid #64B5F6;">
            <i class="ri-add-line me-1"></i> Tambah Baris
          </button>

          <div class="d-flex gap-2">
            <button type="submit"
                    class="btn btn-sm rounded-pill px-4 shadow-sm hover-glow"
                    style="background-color:#FF9800;color:white;"
                    :disabled="hasErrors()">
              <i class="ri-save-3-line me-1"></i> Simpan Semua
            </button>
            <a href="{{ route('super_admin.item_ins.index') }}"
               class="btn btn-sm rounded-pill px-4"
               style="background-color:#FFF3E0;color:#FF9800;border:1px solid #FFB74D;">
              <i class="ri-arrow-go-back-line me-1"></i> Kembali
            </a>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

{{-- 🎨 STYLE TAMBAHAN --}}
<style>
.smooth-fade { animation: fadeIn 0.6s ease-in-out; }
@keyframes fadeIn { from {opacity:0;transform:translateY(10px);} to {opacity:1;transform:translateY(0);} }

.form-control:focus, .form-select:focus {
  border-color: #FF9800 !important;
  box-shadow: 0 0 0 3px rgba(255,152,0,0.25);
}
.hover-glow:hover {
  background-color: #FFC107 !important;
  box-shadow: 0 0 12px rgba(255,152,0,0.4);
}

.hover-glow:disabled {
  background-color: #ccc !important;
  cursor: not-allowed;
  box-shadow: none;
}

.breadcrumb-link { position: relative; transition: all 0.25s ease; }
.breadcrumb-link::after {
  content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: #FF9800;
  transition: width 0.25s ease;
}
.breadcrumb-link:hover::after { width: 100%; }

.item-row {
  transition: all 0.3s ease;
}

.item-row:hover {
  background-color: #FFF8E1;
  border-radius: 8px;
  padding: 12px;
  margin-left: -12px;
  margin-right: -12px;
}

/* =============================== */
/* 🎯 FIX SELECT2 – BIKIN SAMA */
/* =============================== */
.select2-container--default .select2-selection--single {
    height: 47px !important;
    padding: 8px 12px !important;
    border: none !important;
    border-radius: .375rem !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12) !important;
    border-left: 4px solid #FF9800 !important;
    display: flex !important;
    align-items: center !important;
}

.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--focus .select2-selection--single {
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(255,152,0,0.25) !important;
    border-left: 4px solid #FF9800 !important;
}

.select2-container--default .select2-selection__arrow {
    height: 100% !important;
    right: 10px !important;
}

.select2-container .select2-selection--single .select2-selection__rendered {
    padding-left: 0 !important;
    color: #333 !important;
    font-size: 14px !important;
    line-height: 45px !important;
}

/* Error state untuk select2 */
.select2-container--default.has-error .select2-selection--single {
    border-left: 4px solid #dc3545 !important;
}

/* Responsive */
@media (max-width: 768px) {
  .row.g-3 > [class*="col-"] {
    margin-bottom: 1rem;
  }
}
</style>

{{-- ======================== --}}
{{-- 📌 SELECT2 --}}
{{-- ======================== --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@push('scripts')
{{-- ======================== --}}
{{-- 📌 ALPINE JS SCRIPT --}}
{{-- ======================== --}}
<script>
function multipleInput() {
  return {
    rows: [
      { 
        id: Date.now(), 
        useExpired: true,
        selectedItem: '',
        selectedSupplier: '',
        itemError: '',
        supplierError: ''
      }
    ],
    
    addRow() {
      this.rows.push({
        id: Date.now(),
        useExpired: true,
        selectedItem: '',
        selectedSupplier: '',
        itemError: '',
        supplierError: ''
      });
      
      // Reinitialize select2 untuk row baru
      this.$nextTick(() => {
        this.initSelect2();
      });
    },
    
    removeRow(index) {
      if (this.rows.length > 1) {
        this.rows.splice(index, 1);
        // Re-validate semua rows setelah hapus
        this.rows.forEach((row, idx) => {
          this.validateSupplier(idx);
        });
      }
    },
    
    validateSupplier(currentIndex) {
      const currentRow = this.rows[currentIndex];
      
      // Reset error
      currentRow.supplierError = '';
      currentRow.itemError = '';
      
      // Jika belum pilih item atau supplier, skip validasi
      if (!currentRow.selectedItem || !currentRow.selectedSupplier) {
        return;
      }
      
      // Cek apakah ada barang yang sama dengan supplier yang sama di row sebelumnya
      for (let i = 0; i < currentIndex; i++) {
        const prevRow = this.rows[i];
        
        // Jika barang sama DAN supplier sama
        if (prevRow.selectedItem === currentRow.selectedItem && 
            prevRow.selectedSupplier === currentRow.selectedSupplier) {
          
          currentRow.supplierError = '⚠️ Barang yang sama harus dari supplier berbeda!';
          
          // Tambah class error ke select2
          this.$nextTick(() => {
            const supplierSelect = $(`select[name="items[${currentIndex}][supplier_id]"]`);
            supplierSelect.next('.select2-container').addClass('has-error');
          });
          
          return;
        }
      }
      
      // Jika tidak ada error, hapus class error
      this.$nextTick(() => {
        const supplierSelect = $(`select[name="items[${currentIndex}][supplier_id]"]`);
        supplierSelect.next('.select2-container').removeClass('has-error');
      });
    },
    
    hasErrors() {
      // Cek apakah ada error di semua rows
      return this.rows.some(row => row.supplierError || row.itemError);
    },
    
    initSelect2() {
    $('.select2-dynamic').each(function () {
      if (!$(this).hasClass("select2-hidden-accessible")) {
        $(this).select2({
          width: '100%',
          minimumResultsForSearch: 0,
        }).on('change', function () {
         this.dispatchEvent(new Event('change'));
        });
      }
    });
  },
    
    init() {
      this.$nextTick(() => {
        this.initSelect2();
        
        // Setup event listener untuk select2
        
      });
    }
  }
}


</script>
@endpush
@endsection
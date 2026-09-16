@php
    $rec           = $idRecord ?? null;
    $imgSource     = old('image_source',     $rec?->image_source     ?? '');
    $sigSource     = old('signature_source', $rec?->signature_source ?? '');
    $imgPath       = old('image_path',       $rec?->image_path       ?? '');
    $sigPath       = old('signature_path',   $rec?->signature_path   ?? '');
    $imgUpload     = $rec?->image_upload_path;
    $sigUpload     = $rec?->signature_upload_path;
@endphp

<div class="row g-3">

    {{-- ── Name / ID ── --}}
    <div class="col-md-8">
        <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $rec->name ?? '') }}" required
               placeholder="e.g. Juan Dela Cruz">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="id_number" class="form-label fw-semibold">ID Number <span class="text-danger">*</span></label>
        <input type="text" id="id_number" name="id_number"
               class="form-control @error('id_number') is-invalid @enderror"
               value="{{ old('id_number', $rec->id_number ?? '') }}" required
               placeholder="e.g. 200473">
        @error('id_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- ── Position / Date Hired ── --}}
    <div class="col-md-8">
        <label for="position" class="form-label fw-semibold">Position</label>
        <input type="text" id="position" name="position"
               class="form-control @error('position') is-invalid @enderror"
               value="{{ old('position', $rec->position ?? '') }}"
               placeholder="e.g. Admin Assistant">
        @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="date_hired" class="form-label fw-semibold">Date Hired</label>
        <input type="date" id="date_hired" name="date_hired"
               class="form-control @error('date_hired') is-invalid @enderror"
               value="{{ old('date_hired', isset($rec->date_hired) ? $rec->date_hired->format('Y-m-d') : '') }}">
        @error('date_hired') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- ── Birth Date / Emergency Contact ── --}}
    <div class="col-md-4">
        <label for="birth_date" class="form-label fw-semibold">Birth Date</label>
        <input type="date" id="birth_date" name="birth_date"
               class="form-control @error('birth_date') is-invalid @enderror"
               value="{{ old('birth_date', isset($rec->birth_date) ? $rec->birth_date->format('Y-m-d') : '') }}">
        @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label for="emergency_contact" class="form-label fw-semibold">Emergency Contact</label>
        <input type="text" id="emergency_contact" name="emergency_contact"
               class="form-control @error('emergency_contact') is-invalid @enderror"
               value="{{ old('emergency_contact', $rec->emergency_contact ?? '') }}"
               placeholder="Name: 09XXXXXXXXX">
        @error('emergency_contact') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- ── ID IMAGE ── --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="col-12">
        <div class="card border">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                <strong class="small">ID Image</strong>
            </div>
            <div class="card-body pb-2">

                {{-- Source selector --}}
                <label class="form-label small fw-semibold mb-1">Image Source</label>
                <div class="d-flex gap-4 mb-3">
                    <div class="form-check">
                        <input class="form-check-input img-source-radio" type="radio"
                               name="image_source" id="img_src_none"
                               value=""
                               {{ $imgSource === '' ? 'checked' : '' }}>
                        <label class="form-check-label" for="img_src_none">None</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input img-source-radio" type="radio"
                               name="image_source" id="img_src_network"
                               value="network"
                               {{ $imgSource === 'network' ? 'checked' : '' }}>
                        <label class="form-check-label" for="img_src_network">
                            <i class="bi bi-hdd-network me-1"></i>Network / File Path
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input img-source-radio" type="radio"
                               name="image_source" id="img_src_upload"
                               value="upload"
                               {{ $imgSource === 'upload' ? 'checked' : '' }}>
                        <label class="form-check-label" for="img_src_upload">
                            <i class="bi bi-upload me-1"></i>Upload Image
                        </label>
                    </div>
                </div>

                @error('image_source') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                {{-- Network path panel --}}
                <div id="img_network_panel" class="source-panel {{ $imgSource === 'network' ? '' : 'd-none' }}">
                    <label for="image_path" class="form-label small fw-semibold">Network / File Path</label>
                    <input type="text" id="image_path" name="image_path"
                           class="form-control form-control-sm @error('image_path') is-invalid @enderror"
                           value="{{ $imgPath }}"
                           placeholder="Z:\IT_Files\...\Employee Name.png">
                    <div class="form-text">Windows network or local path to the ID front image.</div>
                    @error('image_path') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Upload panel --}}
                <div id="img_upload_panel" class="source-panel {{ $imgSource === 'upload' ? '' : 'd-none' }}">
                    <label for="image_file" class="form-label small fw-semibold">Select ID Image</label>

                    {{-- Show existing upload thumbnail if editing --}}
                    @if($imgUpload && $imgSource === 'upload')
                    <div class="mb-2 d-flex align-items-center gap-2" id="img_existing_wrap">
                        <img src="{{ route('id-records.image', $rec) }}"
                             alt="Current ID Image"
                             class="img-thumbnail" style="max-height:80px;"
                             onerror="this.style.display='none'">
                        <small class="text-muted">Current upload — choose a new file to replace it.</small>
                    </div>
                    @endif

                    <input type="file" id="image_file" name="image_file"
                           class="form-control form-control-sm @error('image_file') is-invalid @enderror"
                           accept=".jpg,.jpeg,.png,.webp"
                           onchange="previewImage(this, 'img_preview')">
                    <div class="form-text">JPG, PNG, WebP — max 5 MB.</div>
                    @error('image_file') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    {{-- Client-side preview --}}
                    <div id="img_preview" class="mt-2 d-none">
                        <img src="" alt="Preview" class="img-thumbnail d-block mb-1" style="max-height:150px;">
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <i class="bi bi-file-image"></i>
                            <span class="img-preview-name"></span>
                            <span class="img-preview-size text-muted ms-1"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 ms-2"
                                    onclick="clearFileInput('image_file', 'img_preview')">
                                <i class="bi bi-x"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- ── SIGNATURE ── --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="col-12">
        <div class="card border">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <i class="bi bi-pen text-secondary"></i>
                <strong class="small">Signature Image</strong>
            </div>
            <div class="card-body pb-2">

                <label class="form-label small fw-semibold mb-1">Signature Source</label>
                <div class="d-flex gap-4 mb-3">
                    <div class="form-check">
                        <input class="form-check-input sig-source-radio" type="radio"
                               name="signature_source" id="sig_src_none"
                               value=""
                               {{ $sigSource === '' ? 'checked' : '' }}>
                        <label class="form-check-label" for="sig_src_none">None</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input sig-source-radio" type="radio"
                               name="signature_source" id="sig_src_network"
                               value="network"
                               {{ $sigSource === 'network' ? 'checked' : '' }}>
                        <label class="form-check-label" for="sig_src_network">
                            <i class="bi bi-hdd-network me-1"></i>Network / File Path
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input sig-source-radio" type="radio"
                               name="signature_source" id="sig_src_upload"
                               value="upload"
                               {{ $sigSource === 'upload' ? 'checked' : '' }}>
                        <label class="form-check-label" for="sig_src_upload">
                            <i class="bi bi-upload me-1"></i>Upload Signature
                        </label>
                    </div>
                </div>

                @error('signature_source') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                {{-- Network path panel --}}
                <div id="sig_network_panel" class="source-panel {{ $sigSource === 'network' ? '' : 'd-none' }}">
                    <label for="signature_path" class="form-label small fw-semibold">Network / File Path</label>
                    <input type="text" id="signature_path" name="signature_path"
                           class="form-control form-control-sm @error('signature_path') is-invalid @enderror"
                           value="{{ $sigPath }}"
                           placeholder="Z:\IT_Files\...\Employee Signature.png">
                    <div class="form-text">Windows network or local path to the signature image.</div>
                    @error('signature_path') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Upload panel --}}
                <div id="sig_upload_panel" class="source-panel {{ $sigSource === 'upload' ? '' : 'd-none' }}">
                    <label for="signature_file" class="form-label small fw-semibold">Select Signature Image</label>

                    @if($sigUpload && $sigSource === 'upload')
                    <div class="mb-2 d-flex align-items-center gap-2" id="sig_existing_wrap">
                        <img src="{{ route('id-records.signature', $rec) }}"
                             alt="Current Signature"
                             class="img-thumbnail" style="max-height:60px;"
                             onerror="this.style.display='none'">
                        <small class="text-muted">Current upload — choose a new file to replace it.</small>
                    </div>
                    @endif

                    <input type="file" id="signature_file" name="signature_file"
                           class="form-control form-control-sm @error('signature_file') is-invalid @enderror"
                           accept=".jpg,.jpeg,.png,.webp"
                           onchange="previewImage(this, 'sig_preview')">
                    <div class="form-text">JPG, PNG, WebP — max 5 MB.</div>
                    @error('signature_file') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    <div id="sig_preview" class="mt-2 d-none">
                        <img src="" alt="Preview" class="img-thumbnail d-block mb-1" style="max-height:80px;">
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <i class="bi bi-file-image"></i>
                            <span class="img-preview-name"></span>
                            <span class="img-preview-size text-muted ms-1"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 ms-2"
                                    onclick="clearFileInput('signature_file', 'sig_preview')">
                                <i class="bi bi-x"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- ── JS: source toggle + file preview ── --}}
<script>
(function () {
    // Show/hide panels when source radio changes
    function bindSourceToggle(radioSelector, networkPanelId, uploadPanelId) {
        document.querySelectorAll(radioSelector).forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.getElementById(networkPanelId).classList.toggle('d-none', this.value !== 'network');
                document.getElementById(uploadPanelId).classList.toggle('d-none',  this.value !== 'upload');
            });
        });
    }

    bindSourceToggle('.img-source-radio', 'img_network_panel', 'img_upload_panel');
    bindSourceToggle('.sig-source-radio', 'sig_network_panel', 'sig_upload_panel');
})();

function previewImage(input, previewId) {
    var wrap = document.getElementById(previewId);
    if (!input.files || !input.files[0]) {
        wrap.classList.add('d-none');
        return;
    }
    var file = input.files[0];
    var maxBytes = 5 * 1024 * 1024;
    if (file.size > maxBytes) {
        wrap.classList.add('d-none');
        return; // server will catch the actual error
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        wrap.classList.remove('d-none');
        wrap.querySelector('img').src = e.target.result;
        wrap.querySelector('.img-preview-name').textContent = file.name;
        wrap.querySelector('.img-preview-size').textContent = '(' + (file.size / 1024).toFixed(0) + ' KB)';
    };
    reader.readAsDataURL(file);
}

function clearFileInput(inputId, previewId) {
    var input = document.getElementById(inputId);
    input.value = '';
    var wrap = document.getElementById(previewId);
    wrap.classList.add('d-none');
    wrap.querySelector('img').src = '';
}
</script>

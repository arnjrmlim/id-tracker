<div class="row g-3">
    <div class="col-12">
        <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
        <input type="text" id="username" name="username" class="form-control @error('username') is-invalid @enderror"
               value="{{ old('username', $user->username ?? '') }}" required autocomplete="off">
        <div class="form-text">Letters, numbers, underscores, hyphens only.</div>
        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label fw-semibold">Email</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email ?? '') }}" autocomplete="off">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @if(!$user)
    <div class="col-md-6">
        <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
        <input type="password" id="password" name="password"
               class="form-control @error('password') is-invalid @enderror"
               required minlength="8" autocomplete="new-password">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="form-control" required autocomplete="new-password">
    </div>
    @endif
    <div class="col-12">
        <label for="role" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
            @foreach($roles as $value => $label)
            <option value="{{ $value }}" {{ old('role', $user->role?->value ?? '') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
            @endforeach
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

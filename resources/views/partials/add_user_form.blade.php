<form action="{{ route('users.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="name" class="form-label color-blue-secondary">Full Name</label>
        <input type="text" class="form-control" id="name" name="name" placeholder="Enter full name" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label color-blue-secondary">Email address</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
    </div>

    <div class="mb-3">
        <label for="role" class="form-label color-blue-secondary">Role</label>
        <select class="form-select" id="role" name="role" required>
            <option selected disabled>Select a role</option>
            <option value="admin">Admin</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label color-blue-secondary">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Set a password" required>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label color-blue-secondary">Confirm Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Re-type password" required>
    </div>

    <button type="submit" class="btn bg-blue-primary text-white w-100 mt-3">Add User</button>
</form>



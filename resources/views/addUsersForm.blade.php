@extends('layouts.app')

@section('content')
    <div class="bg-white">
        <div class="container">
            <div class="row d-flex justify-content-center">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-6">
                    <div class="card shadow-lg  rounded my-5 mx-auto"
                        style="max-width: 600px; background: #fff;">
                        <div class="card-body p-5">
                            <h1 class="card-title mb-4 color-blue-primary">Add User</h1>
                            @include('partials.add_user_form')
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

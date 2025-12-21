@extends('errors.layout')

@section('title', 'Sign in required')
@section('eyebrow', '401 / Unauthorized')
@section('code', '401')
@section('heading', 'Sign in required')
@section('message')
    You need to be signed in to continue. Please log in or return to the home page.
@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('login') }}">
        <i data-lucide="log-in"></i>
        <span>Sign in</span>
    </a>
    <a class="btn btn-ghost" href="{{ route('home') }}">
        <i data-lucide="home"></i>
        <span>Go to home</span>
    </a>
@endsection

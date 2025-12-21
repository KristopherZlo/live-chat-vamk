@extends('errors.layout')

@section('title', 'Service unavailable')
@section('eyebrow', '503 / Service Unavailable')
@section('code', '503')
@section('heading', "We'll be back soon")
@section('message')
    The service is temporarily unavailable. Please try again shortly.
@endsection
@section('actions')
    <button class="btn btn-primary" type="button" onclick="location.reload()">
        <i data-lucide="rotate-cw"></i>
        <span>Try again</span>
    </button>
    <a class="btn btn-ghost" href="{{ route('home') }}">
        <i data-lucide="home"></i>
        <span>Go to home</span>
    </a>
@endsection

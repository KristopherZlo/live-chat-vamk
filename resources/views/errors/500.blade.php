@extends('errors.layout')

@section('title', 'Server error')
@section('eyebrow', '500 / Server Error')
@section('code', '500')
@section('heading', 'Something went wrong')
@section('message')
    We ran into a problem on our side. Please try again in a moment.
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

@extends('errors.layout')

@section('title', 'Too many requests')
@section('eyebrow', '429 / Too Many Requests')
@section('code', '429')
@section('heading', 'Slow down')
@section('message')
    You're sending requests too quickly. Please wait a moment and try again.
@endsection
@section('actions')
    <button class="btn btn-primary" type="button" onclick="history.back()">
        <i data-lucide="arrow-left"></i>
        <span>Go back</span>
    </button>
    <a class="btn btn-ghost" href="{{ route('home') }}">
        <i data-lucide="home"></i>
        <span>Go to home</span>
    </a>
@endsection

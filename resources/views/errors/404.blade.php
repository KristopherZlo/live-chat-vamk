@extends('errors.layout')

@section('title', 'Page not found ¢?÷')
@section('eyebrow', '404 / Not Found')
@section('code', '404')
@section('heading', 'This page went ghost')
@section('message')
    We couldn't find what you were looking for. It might have been renamed, moved, or never existed.
@endsection
@section('actions')
    <button class="btn btn-primary" type="button" onclick="history.back()">
        <i data-lucide="arrow-left"></i>
        <span>Go back</span>
    </button>
@endsection

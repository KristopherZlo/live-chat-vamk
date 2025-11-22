@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'field-control w-full']) }}>

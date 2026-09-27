@props(['disabled' => false])

<input type="checkbox" {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'rounded-md shadow-sm border-gray-300 text-indigo-600 focus:ring focus:ring-indigo-200 focus:ring-opacity-50']) !!}>

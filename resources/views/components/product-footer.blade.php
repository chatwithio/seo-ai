@props([
    'variant' => 'dark',
])

@php
    $isAdmin = $variant === 'admin';
    $footerClasses = $isAdmin
        ? 'mx-auto mt-8 w-full max-w-7xl border-t border-gray-200 px-4 pb-6 pt-5 text-center dark:border-gray-700 sm:px-6 lg:px-8'
        : 'mt-8 border-t border-gray-700/60 pt-5 text-center';
    $linkClasses = $isAdmin
        ? 'cursor-pointer font-medium text-gray-600 transition-colors duration-200 hover:text-primary-600 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-gray-300 dark:hover:text-primary-400 motion-reduce:transition-none'
        : 'cursor-pointer font-medium text-gray-300 transition-colors duration-200 hover:text-amber-300 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 motion-reduce:transition-none';
@endphp

<footer class="{{ $footerClasses }}" aria-label="Our products">
    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Our products</p>
    <nav class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm" aria-label="Product websites">
        <a
            href="https://tochat.be"
            target="_blank"
            rel="noopener noreferrer"
            class="{{ $linkClasses }}"
        >
            Tochat.be
        </a>
        <a
            href="https://social.tochat.be"
            target="_blank"
            rel="noopener noreferrer"
            class="{{ $linkClasses }}"
        >
            Social.tochat.be
        </a>
        <a
            href="https://seoai.tochat.be"
            target="_blank"
            rel="noopener noreferrer"
            class="{{ $linkClasses }}"
        >
            SEOAI.tochat.be
        </a>
    </nav>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">&copy; {{ now()->year }} ChatWithSEO</p>
</footer>

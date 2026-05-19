<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login } from '@/routes';
    import { redirect as kickRedirect } from '@/routes/auth/kick';

    const auth = $derived(page.props.auth);
</script>

<AppHead title="TROLUNAL — Dead by Daylight">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
</AppHead>

<div
    class="relative min-h-screen overflow-hidden bg-[#0a0708] text-neutral-100 antialiased"
>
    <!-- Atmosfer: Antite ışıması + sis -->
    <div class="pointer-events-none absolute inset-0">
        <div
            class="entity-glow absolute top-[-25%] left-1/2 h-[65vh] w-[65vh] -translate-x-1/2 rounded-full bg-[#7f1d1d]/40 blur-[130px]"
        ></div>
        <div
            class="fog absolute bottom-[-35%] left-1/2 h-[60vh] w-[140vw] -translate-x-1/2 rounded-[50%] bg-gradient-to-t from-[#1c0d0d] via-[#130a0b]/70 to-transparent blur-3xl"
        ></div>
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_50%,#000_100%)]"
        ></div>
    </div>

    <!-- Üst bar -->
    <header class="relative z-10 mx-auto max-w-5xl px-6 pt-6">
        <nav class="flex items-center justify-between">
            <span
                class="text-sm font-semibold tracking-[0.3em] text-neutral-500 uppercase"
            >
                TROLUNAL
            </span>
            {#if auth.user}
                <Link
                    href={toUrl(dashboard())}
                    class="rounded-md border border-neutral-800 px-4 py-1.5 text-sm text-neutral-300 transition hover:border-neutral-600 hover:text-white"
                >
                    Panel
                </Link>
            {:else}
                <Link
                    href={toUrl(login())}
                    class="rounded-md px-4 py-1.5 text-sm text-neutral-500 transition hover:text-neutral-300"
                >
                    Yönetici girişi
                </Link>
            {/if}
        </nav>
    </header>

    <main
        class="relative z-10 mx-auto flex min-h-[calc(100vh-7rem)] max-w-3xl flex-col items-center justify-center px-6 text-center"
    >
        <p
            class="mb-6 inline-flex items-center gap-2 rounded-full border border-red-900/50 bg-red-950/30 px-4 py-1.5 text-xs font-medium tracking-[0.25em] text-red-300/90 uppercase"
        >
            <span class="relative flex h-1.5 w-1.5">
                <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"
                ></span>
                <span
                    class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-500"
                ></span>
            </span>
            Dead by Daylight Yayınları
        </p>

        <h1
            class="bg-gradient-to-b from-white via-neutral-200 to-neutral-500 bg-clip-text text-6xl font-black tracking-tight text-transparent drop-shadow-[0_0_30px_rgba(220,38,38,0.35)] sm:text-7xl lg:text-8xl"
        >
            TROLUNAL
        </h1>

        <p
            class="mt-6 max-w-xl text-base leading-relaxed text-neutral-400 sm:text-lg"
        >
            Sise hoş geldin. Jeneratörleri tamir et, kancadan kurtul, Antite'nin
            karanlığından sıyrıl ve topluluğa katıl. Kick ile giriş yap; DBD wiki,
            komutlar ve yayıncıya doğrudan mesaj artık senin elinde.
        </p>

        <div class="mt-10 flex w-full flex-col items-center gap-3">
            {#if auth.user}
                <Link
                    href={toUrl(dashboard())}
                    class="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-xl bg-[#53fc18] px-8 py-3.5 text-base font-bold text-black shadow-[0_0_30px_-5px_rgba(83,252,24,0.6)] transition hover:bg-[#6bff3a] hover:shadow-[0_0_40px_-5px_rgba(83,252,24,0.8)]"
                >
                    Panele Git
                </Link>
            {:else}
                <a
                    href={kickRedirect().url}
                    data-test="kick-login"
                    class="inline-flex w-full max-w-xs items-center justify-center gap-2.5 rounded-xl bg-[#53fc18] px-8 py-3.5 text-base font-bold text-black shadow-[0_0_30px_-5px_rgba(83,252,24,0.6)] transition hover:bg-[#6bff3a] hover:shadow-[0_0_40px_-5px_rgba(83,252,24,0.8)]"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M3 3h5v6l6-6h6l-8 9 8 9h-6l-6-6v6H3z"
                        />
                    </svg>
                    Kick ile Giriş Yap
                </a>
            {/if}
            <p class="text-xs text-neutral-600">
                Yalnızca Kick hesabınla — parola yok, saniyeler içinde içeridesin.
            </p>
        </div>

        <div
            class="mt-16 flex flex-wrap items-center justify-center gap-2 text-xs text-neutral-500"
        >
            <span
                class="rounded-full border border-neutral-800 bg-neutral-900/60 px-3 py-1"
            >
                🔪 Katiller
            </span>
            <span
                class="rounded-full border border-neutral-800 bg-neutral-900/60 px-3 py-1"
            >
                🔦 Survivorlar
            </span>
            <span
                class="rounded-full border border-neutral-800 bg-neutral-900/60 px-3 py-1"
            >
                📖 DBD Wiki
            </span>
            <span
                class="rounded-full border border-neutral-800 bg-neutral-900/60 px-3 py-1"
            >
                💬 Yayıncıya Mesaj
            </span>
        </div>
    </main>

    <footer
        class="relative z-10 pb-8 text-center text-xs tracking-wide text-neutral-700"
    >
        TROLUNAL · Kick'te canlı · Sis seni izliyor 👁️
    </footer>
</div>

<style>
    @keyframes entity-pulse {
        0%,
        100% {
            opacity: 0.35;
            transform: translateX(-50%) scale(1);
        }
        50% {
            opacity: 0.55;
            transform: translateX(-50%) scale(1.08);
        }
    }

    @keyframes fog-drift {
        0%,
        100% {
            transform: translateX(-50%) translateY(0);
            opacity: 0.8;
        }
        50% {
            transform: translateX(-48%) translateY(-12px);
            opacity: 1;
        }
    }

    .entity-glow {
        animation: entity-pulse 7s ease-in-out infinite;
    }

    .fog {
        animation: fog-drift 14s ease-in-out infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        .entity-glow,
        .fog {
            animation: none;
        }
    }
</style>

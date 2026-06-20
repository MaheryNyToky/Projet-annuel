<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Dashboard - Kamoro Hotel</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --sand-50: #fbf4ea;
            --sand-100: #f4eadc;
            --ink: #231f1b;
            --muted: #75695e;
            --accent: #1f7665;
            --line: rgba(68, 52, 39, 0.12);
        }

        body {
            font-family: "Manrope", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.9), transparent 28%),
                linear-gradient(180deg, #f8f0e6 0%, #f5ecdf 100%);
            color: var(--ink);
        }

        .display-serif {
            font-family: "Cormorant Garamond", serif;
        }

        .panel {
            background: rgba(251, 244, 234, 0.92);
            border: 1px solid var(--line);
            box-shadow: 0 18px 44px rgba(78, 62, 48, 0.10);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="min-h-screen">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid w-full grid-cols-1 gap-6 lg:grid-cols-12">
            <section class="lg:col-span-7">
                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Kamoro Hotel</p>
                <h1 class="display-serif mt-3 text-5xl leading-none text-[var(--ink)] sm:text-6xl lg:text-7xl">Dashboard Admin</h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-[var(--muted)]">
                    Connecte-toi avec le meme email et mot de passe que l'application, mais seuls les comptes administrateur et superadmin peuvent entrer ici.
                </p>
                <div class="mt-8 grid max-w-2xl gap-3 text-sm text-[var(--muted)] sm:grid-cols-2">
                    <div class="rounded-3xl border border-[var(--line)] bg-white/60 p-4">
                        <p class="font-bold text-[var(--ink)]">Acces reserve</p>
                        <p class="mt-1">Compte admin ou superadmin uniquement. Les comptes reception ne peuvent pas ouvrir ce tableau de bord.</p>
                    </div>
                    <div class="rounded-3xl border border-[var(--line)] bg-white/60 p-4">
                        <p class="font-bold text-[var(--ink)]">Meme base utilisateurs</p>
                        <p class="mt-1">Le login utilise la table `users` deja partagee avec l'application.</p>
                    </div>
                </div>
            </section>

            <section class="lg:col-span-5">
                <div class="panel rounded-[28px] p-6 sm:p-8">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Connexion</p>
                    <h2 class="display-serif mt-3 text-4xl leading-none text-[var(--ink)]">Administrateur</h2>

                    @if ($errors->any())
                        <div class="mt-6 rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('dashboard.login.submit') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="email" class="mb-2 block text-sm font-bold text-[var(--ink)]">Email</label>
                            <input id="email" name="email" type="email" required value="{{ old('email') }}" class="h-12 w-full rounded-2xl border border-[var(--line)] bg-white/80 px-4 text-sm outline-none focus:border-[var(--accent)]">
                        </div>
                        <div>
                            <label for="password" class="mb-2 block text-sm font-bold text-[var(--ink)]">Mot de passe</label>
                            <input id="password" name="password" type="password" required class="h-12 w-full rounded-2xl border border-[var(--line)] bg-white/80 px-4 text-sm outline-none focus:border-[var(--accent)]">
                        </div>
                        <button type="submit" class="h-12 w-full rounded-2xl bg-[var(--ink)] text-sm font-extrabold text-[#fbf4ea] transition hover:opacity-90">
                            Entrer
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</body>
</html>

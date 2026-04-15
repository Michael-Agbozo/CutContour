<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — CutContour</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cut:    #ec008c;
            --cut-06: rgba(236,0,140,.06);
            --cut-20: rgba(236,0,140,.20);
            --green:  #22c55e;
        }
        [data-theme="dark"] {
            --bg:      #0e0e0d;
            --bg-2:    #161614;
            --bg-3:    #1e1d1b;
            --surface: #232220;
            --border:  rgba(255,255,255,.08);
            --border-2:rgba(255,255,255,.15);
            --text:    #f0ede7;
            --text-2:  #9a9690;
            --text-3:  rgba(255,255,255,.25);
            --dot:     rgba(255,255,255,.07);
        }
        [data-theme="light"] {
            --bg:      #f5f4f0;
            --bg-2:    #ffffff;
            --bg-3:    #eceae4;
            --surface: #ffffff;
            --border:  rgba(0,0,0,.09);
            --border-2:rgba(0,0,0,.18);
            --text:    #111110;
            --text-2:  #706d66;
            --text-3:  rgba(0,0,0,.30);
            --dot:     rgba(0,0,0,.07);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html   { font-family: 'DM Sans', sans-serif; }
        body   { background: var(--bg); color: var(--text); min-height: 100svh;
                 transition: background .3s, color .3s; }
        a      { text-decoration: none; }

        .serif { font-family: 'Cormorant Garamond', Georgia, serif; }
        .mono  { font-family: 'Space Mono', monospace; }

        .dots {
            background-image: radial-gradient(circle, var(--dot) 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }

        /* ── Layout ───────────────────────────────────────── */
        .shell {
            display: grid;
            grid-template-columns: 1fr;
            min-height: 100svh;
        }
        @media (min-width: 1024px) {
            .shell { grid-template-columns: 420px 1fr; }
        }

        /* ── Brand panel (left) ───────────────────────────── */
        .brand {
            display: none;
            flex-direction: column;
            background: var(--bg-3);
            border-right: 1px solid var(--border);
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        @media (min-width: 1024px) { .brand { display: flex; } }

        .brand-top {
            display: flex; align-items: center; justify-content: space-between;
        }
        .brand-logo {
            display: flex; align-items: center; gap: .625rem;
        }
        .brand-logo-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 500; color: var(--text);
        }
        .brand-back {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .08em; text-transform: uppercase;
            color: var(--text-3); transition: color .2s;
        }
        .brand-back:hover { color: var(--text-2); }

        .brand-body {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; padding: 2rem 0;
        }
        .brand-eyebrow {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--cut); margin-bottom: 1rem;
        }
        .brand-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.4rem; font-weight: 300; line-height: 1.15;
            color: var(--text); margin-bottom: 2rem;
        }
        .brand-headline em { font-style: italic; color: var(--cut); }

        .brand-benefits { display: flex; flex-direction: column; gap: 0; }
        .brand-benefit {
            display: flex; align-items: flex-start; gap: .875rem;
            padding: .875rem 0;
            border-top: 1px solid var(--border);
        }
        .brand-benefit:last-child { border-bottom: 1px solid var(--border); }
        .benefit-check {
            width: 16px; height: 16px; flex-shrink: 0;
            border: 1.5px solid var(--cut); border-radius: 2px;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }
        .benefit-title { font-size: .8125rem; font-weight: 500; color: var(--text); margin-bottom: .15rem; }
        .benefit-sub   { font-size: .75rem; color: var(--text-3); line-height: 1.5; }

        /* Mini canvas at brand bottom */
        .brand-canvas {
            margin-top: 2.5rem;
            border: 1px solid var(--border);
            border-radius: 6px; overflow: hidden;
            opacity: .65;
        }
        .mini-toolbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            height: 32px; display: flex; align-items: center;
            justify-content: space-between; padding: 0 .75rem;
        }
        .mini-dots { display: flex; gap: 4px; }
        .mini-dot  { width: 8px; height: 8px; border-radius: 50%; }
        .mini-canvas-body {
            padding: 1rem; display: flex;
            justify-content: center; align-items: center;
        }
        .mini-artboard {
            width: 120px; height: 90px; background: white;
            border-radius: 2px; position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,.35);
        }

        /* ── Form panel (right) ───────────────────────────── */
        .form-panel {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 2rem 1.5rem;
            min-height: 100svh;
            position: relative;
        }
        .form-panel-top {
            position: absolute; top: 1.25rem; right: 1.25rem;
            display: flex; align-items: center; gap: .5rem;
        }

        /* Mobile logo (shown when brand panel is hidden) */
        .mobile-logo {
            display: flex; align-items: center; gap: .5rem;
            margin-bottom: 2.5rem;
        }
        @media (min-width: 1024px) { .mobile-logo { display: none; } }

        .form-wrap {
            width: 100%; max-width: 400px;
        }
        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem; font-weight: 300; color: var(--text);
            margin-bottom: .5rem; line-height: 1.15;
        }
        .form-sub {
            font-size: .875rem; color: var(--text-2); margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* ── Inputs ──────────────────────────────────────── */
        .field { margin-bottom: 1.25rem; }
        .field-row { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 480px) { .field-row-2 { grid-template-columns: 1fr 1fr; } }

        .label {
            font-family: 'Space Mono', monospace;
            font-size: .58rem; letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-2); display: block; margin-bottom: .4rem;
        }
        .label-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: .4rem;
        }
        .label-row .label { margin-bottom: 0; }

        .input-wrap { position: relative; }
        .input {
            width: 100%; padding: .6875rem .875rem;
            border: 1px solid var(--border);
            border-radius: 3px;
            background: var(--surface);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .9375rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            -webkit-appearance: none;
        }
        .input::placeholder { color: var(--text-3); }
        .input:focus {
            border-color: var(--cut);
            box-shadow: 0 0 0 3px var(--cut-06);
        }
        .input.has-toggle { padding-right: 2.75rem; }
        @error('email')    .input:not(:focus) { border-color: var(--cut); } @enderror

        .toggle-vis {
            position: absolute; right: .75rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-3); padding: .25rem;
            transition: color .2s; line-height: 0;
        }
        .toggle-vis:hover { color: var(--text-2); }

        .error-msg {
            font-size: .78rem; color: var(--cut);
            margin-top: .35rem; display: block;
        }

        /* ── Checkbox ────────────────────────────────────── */
        .check-row {
            display: flex; align-items: center; gap: .625rem;
            cursor: pointer;
        }
        .check-box {
            width: 15px; height: 15px; flex-shrink: 0;
            border: 1.5px solid var(--border-2); border-radius: 2px;
            background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            transition: border-color .15s, background .15s;
            cursor: pointer;
        }
        input[type="checkbox"]:checked + .check-box {
            background: var(--cut); border-color: var(--cut);
        }
        input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .check-label { font-size: .875rem; color: var(--text-2); cursor: pointer; }

        /* ── Buttons ─────────────────────────────────────── */
        .btn-primary {
            display: flex; align-items: center; justify-content: center;
            width: 100%; padding: .75rem 1.5rem;
            background: var(--cut); color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: .9375rem; font-weight: 500;
            border: none; border-radius: 3px; cursor: pointer;
            transition: background .2s, transform .15s;
        }
        .btn-primary:hover { background: #d4007e; transform: translateY(-1px); }
        .btn-primary:active { transform: scale(.99); }

        /* ── Divider ─────────────────────────────────────── */
        .divider {
            border: none; border-top: 1px solid var(--border);
            margin: 1.75rem 0;
        }
        .link-row {
            text-align: center; font-size: .875rem; color: var(--text-2);
        }
        .link-row a { color: var(--cut); transition: opacity .2s; }
        .link-row a:hover { opacity: .75; }

        /* ── Status message ──────────────────────────────── */
        .status-msg {
            background: var(--cut-06); border: 1px solid var(--cut-20);
            border-radius: 3px; padding: .75rem 1rem;
            font-size: .8125rem; color: var(--text-2);
            margin-bottom: 1.25rem;
        }

        /* ── Theme toggle ────────────────────────────────── */
        #theme-toggle {
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 4px; border: 1px solid var(--border);
            background: transparent; cursor: pointer; color: var(--text-2);
            transition: border-color .2s, color .2s;
        }
        #theme-toggle:hover { border-color: var(--border-2); color: var(--cut); }
        [data-theme="dark"]  .icon-moon { display: none; }
        [data-theme="light"] .icon-sun  { display: none; }

        /* ── SVG cut path animation ──────────────────────── */
        @keyframes trace { 0% { stroke-dashoffset:400;opacity:0; } 5%{opacity:1;} 80%{stroke-dashoffset:0;} 100%{stroke-dashoffset:0;opacity:1;} }
        @keyframes blink  { 0%,100%{opacity:1;} 50%{opacity:.2;} }
        .cut-anim {
            stroke-dasharray: 400; stroke-dashoffset: 400;
            animation: trace 3s ease 1s forwards;
        }
        .blink { animation: blink 2.5s ease-in-out infinite; }

        .reg {
            position: absolute; width: 18px; height: 18px; opacity: .15; pointer-events: none;
        }
        .reg::before,.reg::after { content:''; position:absolute; background:var(--text); }
        .reg::before { width:1px;height:100%;left:50%;transform:translateX(-50%); }
        .reg::after  { height:1px;width:100%;top:50%;transform:translateY(-50%); }
    </style>
</head>
<body>
<div class="shell dots">

    {{-- ── Brand panel ─────────────────────────────────── --}}
    <div class="brand dots">
        <div class="reg" style="top:1.5rem;left:1.5rem;"></div>
        <div class="reg" style="bottom:1.5rem;right:1.5rem;"></div>

        <div class="brand-top">
            <a href="{{ route('home') }}" class="brand-logo">
                <svg width="24" height="24" viewBox="0 0 26 26" fill="none">
                    <rect x="1.5" y="1.5" width="23" height="23" rx="4" stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                    <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c"/>
                </svg>
                <span class="brand-logo-text">CutContour</span>
            </a>
            <a href="{{ route('home') }}" class="brand-back">← Home</a>
        </div>

        <div class="brand-body">
            <div class="brand-eyebrow">Welcome back</div>
            <h2 class="brand-headline">
                Cut paths.<br><em>Automated.</em>
            </h2>

            <div class="brand-benefits">
                @foreach([
                    ['< 30 seconds', 'Upload to print-ready PDF in under half a minute.'],
                    ['RIP-compatible', 'CutContour spot colour — CMYK 0·100·0·0 — works out of the box.'],
                    ['90-day storage', 'Re-download any job for three months.'],
                ] as [$title, $sub])
                <div class="brand-benefit">
                    <div class="benefit-check">
                        <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                            <path d="M1 4l2 2 4-4" stroke="#ec008c" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <div class="benefit-title">{{ $title }}</div>
                        <div class="benefit-sub">{{ $sub }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Mini canvas mockup --}}
        <div class="brand-canvas">
            <div class="mini-toolbar">
                <div class="mini-dots">
                    <span class="mini-dot" style="background:#ff5f57;"></span>
                    <span class="mini-dot" style="background:#febc2e;"></span>
                    <span class="mini-dot" style="background:#28c840;"></span>
                </div>
                <span class="mono" style="font-size:.55rem;color:var(--text-3);letter-spacing:.06em;">artwork.pdf</span>
                <div style="display:flex;align-items:center;gap:4px;">
                    <span class="blink" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#22c55e;"></span>
                    <span class="mono" style="font-size:.55rem;color:var(--text-3);">Ready</span>
                </div>
            </div>
            <div class="mini-canvas-body dots" style="background:var(--bg-2);">
                <div class="mini-artboard">
                    <svg viewBox="0 0 120 90" style="position:absolute;inset:0;width:100%;height:100%;">
                        <rect class="cut-anim" x="5" y="5" width="110" height="80" rx="10"
                              fill="none" stroke="#ec008c" stroke-width="1.5"
                              stroke-dasharray="7 3.5" stroke-linecap="round"/>
                        <circle cx="50" cy="40" r="18" fill="rgba(0,0,0,.08)"/>
                        <rect x="38" y="62" width="24" height="5" rx="1" fill="rgba(0,0,0,.07)"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Form panel ────────────────────────────────────── --}}
    <div class="form-panel">
        <div class="form-panel-top">
            <a href="{{ route('home') }}" class="mono" style="font-size:.6rem;letter-spacing:.08em;color:var(--text-3);text-transform:uppercase;display:none;" id="desktop-home-link">← Home</a>
            <button id="theme-toggle" aria-label="Toggle theme">
                <svg class="icon-sun" width="15" height="15" viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="3.5" stroke="currentColor" stroke-width="1.25"/>
                    <path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.6 3.4l-1.1 1.1M4.5 11.5l-1.1 1.1M12.6 12.6l-1.1-1.1M4.5 4.5L3.4 3.4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"/>
                </svg>
                <svg class="icon-moon" width="14" height="14" viewBox="0 0 15 15" fill="none">
                    <path d="M13 9.5A6.5 6.5 0 015.5 2 6.5 6.5 0 1013 9.5z" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div class="form-wrap">

            {{-- Mobile logo --}}
            <div class="mobile-logo">
                <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
                    <rect x="1.5" y="1.5" width="23" height="23" rx="4" stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                    <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c"/>
                </svg>
                <span class="serif" style="font-size:1.1rem;font-weight:500;color:var(--text);">CutContour</span>
            </div>

            <h1 class="form-title">Welcome back.</h1>
            <p class="form-sub">Sign in to your account to continue.</p>

            {{-- Session status --}}
            @if (session('status'))
            <div class="status-msg">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                {{-- Email --}}
                <div class="field">
                    <label class="label" for="email">Email address</label>
                    <input id="email" name="email" type="email"
                           class="input {{ $errors->has('email') ? 'error-border' : '' }}"
                           value="{{ old('email') }}"
                           placeholder="you@example.com"
                           autocomplete="email" autofocus required>
                    @error('email')
                        <span class="error-msg">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="field">
                    <div class="label-row">
                        <label class="label" for="password">Password</label>
                        @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="mono"
                           style="font-size:.58rem;letter-spacing:.06em;color:var(--cut);text-decoration:none;transition:opacity .2s;"
                           onmouseover="this.style.opacity='.7'"
                           onmouseout="this.style.opacity='1'">
                            Forgot password?
                        </a>
                        @endif
                    </div>
                    <div class="input-wrap">
                        <input id="password" name="password" type="password"
                               class="input has-toggle"
                               placeholder="••••••••"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-vis" data-target="password" aria-label="Show password">
                            <svg class="eye-open" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.2"/>
                                <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.2"/>
                            </svg>
                            <svg class="eye-closed" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none;">
                                <path d="M2 2l12 12M6.5 6.6A2 2 0 0010 10M4.2 4.3C2.6 5.4 1 8 1 8s2.5 5 7 5c1.4 0 2.6-.4 3.7-1M9 3.2C9.7 3.1 10.4 3 11 3c3 0 4 5 4 5s-.5 1.1-1.5 2.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="error-msg">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="field" style="margin-bottom:1.75rem;">
                    <label class="check-row">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span class="check-box">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                <path d="M1 4l2 2 4-4" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="check-label">Keep me signed in</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary">Sign in</button>
            </form>

            <hr class="divider">

            @if (Route::has('register'))
            <div class="link-row">
                Don't have an account? <a href="{{ route('register') }}">Create one free</a>
            </div>
            @endif

        </div>
    </div>

</div>

<style>
    .input.error-border { border-color: var(--cut); }
</style>

<script>
    // Theme
    const html  = document.documentElement;
    const saved = localStorage.getItem('cc-theme') || 'dark';
    html.dataset.theme = saved;
    document.getElementById('theme-toggle').addEventListener('click', () => {
        const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = next;
        localStorage.setItem('cc-theme', next);
    });

    // Show/hide password toggle
    document.querySelectorAll('.toggle-vis').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            btn.querySelector('.eye-open').style.display  = isText ? 'block' : 'none';
            btn.querySelector('.eye-closed').style.display = isText ? 'none'  : 'block';
        });
    });

    // Custom checkbox styling
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => {
            const box = cb.nextElementSibling;
            box.style.background = cb.checked ? 'var(--cut)' : 'var(--surface)';
            box.style.borderColor = cb.checked ? 'var(--cut)' : '';
        });
    });
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account — CutContour</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cut:    #ec008c;
            --cut-06: rgba(236,0,140,.06);
            --cut-20: rgba(236,0,140,.20);
        }
        [data-theme="dark"] {
            --bg:      #0e0e0d;
            --bg-2:    #161614;
            --bg-3:    #1e1d1b;
            --surface: #232220;
            --surface-2:#2c2b28;
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
            --surface-2:#f0ede7;
            --border:  rgba(0,0,0,.09);
            --border-2:rgba(0,0,0,.18);
            --text:    #111110;
            --text-2:  #706d66;
            --text-3:  rgba(0,0,0,.30);
            --dot:     rgba(0,0,0,.07);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html   { font-family: 'DM Sans', sans-serif; }
        body   { background: var(--bg); color: var(--text);
                 transition: background .3s, color .3s; }
        a      { text-decoration: none; }

        .serif { font-family: 'Cormorant Garamond', Georgia, serif; }
        .mono  { font-family: 'Space Mono', monospace; }

        .dots {
            background-image: radial-gradient(circle, var(--dot) 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }

        /* ── Page shell ──────────────────────────────────── */
        .page { min-height: 100svh; display: flex; flex-direction: column; }

        /* ── Top bar ────────────────────────────────────── */
        .topbar {
            height: 56px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem; position: sticky; top: 0; z-index: 50;
            background: var(--bg);
        }
        .logo { display: flex; align-items: center; gap: .625rem; }
        .logo-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 500; color: var(--text);
        }
        .topbar-right { display: flex; align-items: center; gap: .75rem; }
        .topbar-sign-in {
            font-size: .8125rem; color: var(--text-2); transition: color .2s;
        }
        .topbar-sign-in:hover { color: var(--text); }

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

        /* ── Main content ────────────────────────────────── */
        .main {
            flex: 1; display: flex; align-items: flex-start;
            justify-content: center; padding: 2.5rem 1.5rem 4rem;
        }

        /* ── Form card ───────────────────────────────────── */
        .form-card {
            width: 100%; max-width: 640px;
        }
        .form-header { margin-bottom: 2rem; }
        .form-eyebrow {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--cut); margin-bottom: .75rem;
        }
        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem; font-weight: 300; color: var(--text);
            line-height: 1.15; margin-bottom: .5rem;
        }
        .form-sub { font-size: .875rem; color: var(--text-2); line-height: 1.6; }

        /* ── Section separators ──────────────────────────── */
        .section-sep {
            display: flex; align-items: center; gap: 1rem;
            margin: 2rem 0 1.5rem;
        }
        .section-sep-line { flex: 1; height: 1px; background: var(--border); }
        .section-sep-label {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-3); white-space: nowrap;
        }

        /* ── Fields ──────────────────────────────────────── */
        .fields-grid {
            display: grid; gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 480px) {
            .fields-grid-2 { grid-template-columns: 1fr 1fr; }
        }

        .field { /* single field */ }
        .label {
            font-family: 'Space Mono', monospace;
            font-size: .58rem; letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-2); display: block; margin-bottom: .4rem;
        }
        .input-wrap { position: relative; }
        .input {
            width: 100%; padding: .6875rem .875rem;
            border: 1px solid var(--border);
            border-radius: 3px; background: var(--surface); color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: .9375rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            -webkit-appearance: none;
        }
        .input::placeholder { color: var(--text-3); }
        .input:focus { border-color: var(--cut); box-shadow: 0 0 0 3px var(--cut-06); }
        .input.has-toggle { padding-right: 2.75rem; }
        .input.is-error   { border-color: var(--cut); }

        .toggle-vis {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-3); padding: .25rem; line-height: 0;
            transition: color .2s;
        }
        .toggle-vis:hover { color: var(--text-2); }

        .error-msg { font-size: .78rem; color: var(--cut); margin-top: .35rem; display: block; }

        /* ── Plan cards ──────────────────────────────────── */
        .plans-grid {
            display: grid; gap: .75rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 420px) { .plans-grid { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 640px) { .plans-grid { grid-template-columns: repeat(3,1fr); } }

        .plan-card {
            border: 1px solid var(--border);
            border-radius: 5px; padding: 1.25rem 1.125rem;
            cursor: pointer; position: relative;
            transition: border-color .2s, background .2s, box-shadow .2s;
            background: var(--surface);
        }
        .plan-card:hover {
            border-color: var(--border-2);
        }
        .plan-card.selected {
            border-color: var(--cut);
            background: var(--cut-06);
            box-shadow: 0 0 0 3px var(--cut-06);
        }
        .plan-radio { position: absolute; opacity: 0; width: 0; height: 0; }

        .plan-radio-indicator {
            width: 14px; height: 14px; border-radius: 50%;
            border: 1.5px solid var(--border-2);
            position: absolute; top: 1rem; right: 1rem;
            transition: border-color .2s, background .2s;
            display: flex; align-items: center; justify-content: center;
        }
        .plan-card.selected .plan-radio-indicator {
            border-color: var(--cut); background: var(--cut);
        }
        .plan-radio-dot {
            width: 5px; height: 5px; border-radius: 50%; background: white;
            opacity: 0; transition: opacity .15s;
        }
        .plan-card.selected .plan-radio-dot { opacity: 1; }

        .plan-name {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .1em; text-transform: uppercase;
            margin-bottom: .625rem;
        }
        .plan-card.selected .plan-name { color: var(--cut); }
        .plan-name:not(.plan-card.selected .plan-name) { color: var(--text-3); }

        .plan-price {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.9rem; font-weight: 300; color: var(--text);
            line-height: 1; margin-bottom: .2rem;
        }
        .plan-price small {
            font-size: 1rem; color: var(--text-2);
        }
        .plan-period { font-size: .75rem; color: var(--text-2); margin-bottom: .875rem; }
        .plan-divider { border: none; border-top: 1px dashed var(--border); margin-bottom: .875rem; }
        .plan-card.selected .plan-divider { border-color: var(--cut-20); }
        .plan-features {
            list-style: none; display: flex; flex-direction: column; gap: .4rem;
        }
        .plan-features li {
            display: flex; gap: .4rem;
            font-size: .75rem; color: var(--text-2); line-height: 1.4;
        }
        .plan-features li span:first-child { color: var(--cut); flex-shrink: 0; }

        .plan-badge {
            position: absolute; top: -.625rem; left: 50%; transform: translateX(-50%);
            background: var(--cut); color: white;
            font-family: 'Space Mono', monospace; font-size: .56rem;
            letter-spacing: .07em; text-transform: uppercase;
            padding: .2rem .6rem; border-radius: 2px; white-space: nowrap;
        }

        /* ── Payment section ─────────────────────────────── */
        #payment-section {
            display: none;
            overflow: hidden;
            transition: opacity .3s;
        }
        #payment-section.visible { display: block; }

        .payment-card {
            border: 1.5px dashed var(--cut-20);
            border-radius: 5px; padding: 1.5rem;
            background: var(--cut-06);
        }
        .payment-header {
            display: flex; align-items: center; gap: .625rem;
            margin-bottom: 1.25rem;
        }
        .payment-header-text {
            font-family: 'Space Mono', monospace;
            font-size: .62rem; letter-spacing: .09em; text-transform: uppercase;
            color: var(--text-2);
        }
        .payment-trial-banner {
            display: flex; align-items: flex-start; gap: .625rem;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 3px; padding: .75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: .8125rem; color: var(--text-2); line-height: 1.55;
        }

        /* Credit card visual */
        .card-visual {
            width: 100%; max-width: 320px;
            height: 90px; border-radius: 8px;
            background: linear-gradient(135deg, #1a1918 0%, #2c2b28 100%);
            border: 1px solid rgba(255,255,255,.12);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        [data-theme="light"] .card-visual {
            background: linear-gradient(135deg, #1e1d1b 0%, #2c2b28 100%);
        }
        .card-visual::before {
            content: ''; position: absolute;
            top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(236,0,140,.12);
        }
        .card-visual::after {
            content: ''; position: absolute;
            bottom: -30px; right: 30px;
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(236,0,140,.07);
        }
        .card-chip {
            width: 28px; height: 20px; border-radius: 3px;
            background: linear-gradient(135deg, #c8a85c, #f0d080);
            position: relative; z-index: 1;
        }
        .card-number-display {
            font-family: 'Space Mono', monospace;
            font-size: .75rem; color: rgba(255,255,255,.6);
            letter-spacing: .12em; position: relative; z-index: 1;
        }

        /* Card input grid */
        .card-inputs { display: flex; flex-direction: column; gap: .875rem; }
        .card-row {
            display: grid; gap: .875rem;
            grid-template-columns: 1fr 1fr;
        }

        /* ── Buttons ─────────────────────────────────────── */
        .btn-primary {
            display: flex; align-items: center; justify-content: center;
            width: 100%; padding: .8125rem 1.5rem;
            background: var(--cut); color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: .9375rem; font-weight: 500;
            border: none; border-radius: 3px; cursor: pointer;
            transition: background .2s, transform .15s;
            margin-top: 1.75rem;
        }
        .btn-primary:hover { background: #d4007e; transform: translateY(-1px); }
        .btn-primary:active { transform: scale(.99); }

        /* ── Footer link ─────────────────────────────────── */
        .form-footer {
            text-align: center; margin-top: 1.5rem;
            font-size: .875rem; color: var(--text-2);
        }
        .form-footer a { color: var(--cut); }
        .form-footer a:hover { opacity: .75; }

        /* ── Status msg ──────────────────────────────────── */
        .status-msg {
            background: var(--cut-06); border: 1px solid var(--cut-20);
            border-radius: 3px; padding: .75rem 1rem;
            font-size: .8125rem; color: var(--text-2);
            margin-bottom: 1.25rem;
        }

        /* ── Checkbox ────────────────────────────────────── */
        .check-wrap { display: flex; align-items: flex-start; gap: .625rem; cursor: pointer; }
        .check-box  {
            width: 15px; height: 15px; flex-shrink: 0; margin-top: 2px;
            border: 1.5px solid var(--border-2); border-radius: 2px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s, border-color .15s;
        }
        .check-txt  { font-size: .8125rem; color: var(--text-2); line-height: 1.55; cursor: pointer; }
        .check-txt a { color: var(--cut); }
        input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
    </style>
</head>
<body class="dots">

<div class="page">

    {{-- ── Top bar ─────────────────────────────────────────── --}}
    <header class="topbar">
        <a href="{{ route('home') }}" class="logo">
            <svg width="24" height="24" viewBox="0 0 26 26" fill="none">
                <rect x="1.5" y="1.5" width="23" height="23" rx="4" stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c"/>
            </svg>
            <span class="logo-text">CutContour</span>
        </a>
        <div class="topbar-right">
            <a href="{{ route('login') }}" class="topbar-sign-in">Already have an account? Sign in →</a>
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
    </header>

    {{-- ── Main ─────────────────────────────────────────────── --}}
    <main class="main">
        <div class="form-card">

            <div class="form-header">
                <div class="form-eyebrow">New account</div>
                <h1 class="form-title">Create your account.</h1>
                <p class="form-sub">Start with 10 free jobs per month, or choose a plan below.</p>
            </div>

            @if (session('status'))
            <div class="status-msg">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" id="register-form">
                @csrf
                <input type="hidden" name="plan" id="plan_value" value="starter">

                {{-- ── Account details ─────────────────────── --}}
                <div class="fields-grid fields-grid-2">
                    <div class="field">
                        <label class="label" for="name">Full name</label>
                        <input id="name" name="name" type="text"
                               class="input {{ $errors->has('name') ? 'is-error' : '' }}"
                               value="{{ old('name') }}"
                               placeholder="Jane Smith"
                               autocomplete="name" autofocus required>
                        @error('name')<span class="error-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="email">Email address</label>
                        <input id="email" name="email" type="email"
                               class="input {{ $errors->has('email') ? 'is-error' : '' }}"
                               value="{{ old('email') }}"
                               placeholder="you@example.com"
                               autocomplete="email" required>
                        @error('email')<span class="error-msg">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="fields-grid fields-grid-2" style="margin-top:1rem;">
                    <div class="field">
                        <label class="label" for="password">Password</label>
                        <div class="input-wrap">
                            <input id="password" name="password" type="password"
                                   class="input has-toggle {{ $errors->has('password') ? 'is-error' : '' }}"
                                   placeholder="Min. 8 characters"
                                   autocomplete="new-password" required>
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
                        @error('password')<span class="error-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="password_confirmation">Confirm password</label>
                        <div class="input-wrap">
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   class="input has-toggle"
                                   placeholder="Repeat password"
                                   autocomplete="new-password" required>
                            <button type="button" class="toggle-vis" data-target="password_confirmation" aria-label="Show password">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.2"/>
                                    <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.2"/>
                                </svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none;">
                                    <path d="M2 2l12 12M6.5 6.6A2 2 0 0010 10M4.2 4.3C2.6 5.4 1 8 1 8s2.5 5 7 5c1.4 0 2.6-.4 3.7-1M9 3.2C9.7 3.1 10.4 3 11 3c3 0 4 5 4 5s-.5 1.1-1.5 2.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Plan selection ───────────────────────── --}}
                <div class="section-sep">
                    <div class="section-sep-line"></div>
                    <span class="section-sep-label">Choose your plan</span>
                    <div class="section-sep-line"></div>
                </div>

                @php
                $plans = [
                    ['starter','Starter','Free',null,'10 jobs / month',false, ['10 jobs / month','Up to 10 MB / file','Standard processing','30-day retention']],
                    ['pro','Pro','$89','/mo','200 jobs / month',true,  ['200 jobs / month','Up to 100 MB / file','AI-enhanced processing','90-day retention','Priority queue']],
                    ['studio','Studio','$250','/mo','Unlimited jobs',false, ['Unlimited jobs','Up to 100 MB / file','AI-enhanced processing','90-day retention','API access (soon)']],
                ];
                @endphp

                <div class="plans-grid">
                    @foreach($plans as [$id,$name,$price,$per,$sub,$popular,$features])
                    <div class="plan-card {{ $id === 'starter' ? 'selected' : '' }}"
                         id="plan-{{ $id }}"
                         data-plan="{{ $id }}"
                         role="radio" aria-checked="{{ $id === 'starter' ? 'true' : 'false' }}"
                         tabindex="0">
                        @if($popular)<span class="plan-badge">Popular</span>@endif
                        <div class="plan-radio-indicator">
                            <div class="plan-radio-dot"></div>
                        </div>
                        <div class="plan-name" style="{{ $id === 'starter' ? 'color:var(--cut);' : 'color:var(--text-3);' }}">
                            {{ $name }}
                        </div>
                        <div class="plan-price">
                            {{ $price }}<small>{{ $per }}</small>
                        </div>
                        <div class="plan-period">{{ $sub }}</div>
                        <hr class="plan-divider">
                        <ul class="plan-features">
                            @foreach($features as $feat)
                            <li><span>—</span><span>{{ $feat }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>

                {{-- ── Payment section ─────────────────────── --}}
                <div id="payment-section">
                    <div class="section-sep">
                        <div class="section-sep-line"></div>
                        <span class="section-sep-label">Payment details</span>
                        <div class="section-sep-line"></div>
                    </div>

                    <div class="payment-card">

                        <div class="payment-trial-banner">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;margin-top:1px;">
                                <circle cx="8" cy="8" r="6.5" stroke="var(--cut)" stroke-width="1.1"/>
                                <path d="M8 5v3.5M8 11v.5" stroke="var(--cut)" stroke-width="1.2" stroke-linecap="round"/>
                            </svg>
                            <span>
                                You won't be charged today. Your <strong id="trial-plan-name" style="color:var(--text);">Pro</strong> plan includes a <strong style="color:var(--text);">14-day free trial.</strong>
                                Billing begins after the trial ends. Cancel anytime.
                            </span>
                        </div>

                        {{-- Card visual --}}
                        <div class="card-visual">
                            <div class="card-chip"></div>
                            <div class="card-number-display" id="card-display">•••• &nbsp;•••• &nbsp;•••• &nbsp;••••</div>
                        </div>

                        {{-- Card inputs (no name attr — handled by Stripe.js in production) --}}
                        <div class="card-inputs">
                            <div class="field">
                                <label class="label" for="card_number">Card number</label>
                                <input id="card_number" type="text"
                                       class="input"
                                       placeholder="1234  5678  9012  3456"
                                       autocomplete="cc-number"
                                       inputmode="numeric"
                                       maxlength="19">
                            </div>
                            <div class="card-row">
                                <div class="field">
                                    <label class="label" for="card_expiry">Expiry</label>
                                    <input id="card_expiry" type="text"
                                           class="input"
                                           placeholder="MM / YY"
                                           autocomplete="cc-exp"
                                           inputmode="numeric"
                                           maxlength="7">
                                </div>
                                <div class="field">
                                    <label class="label" for="card_cvc">CVC</label>
                                    <input id="card_cvc" type="text"
                                           class="input"
                                           placeholder="•••"
                                           autocomplete="cc-csc"
                                           inputmode="numeric"
                                           maxlength="4">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label" for="card_name">Cardholder name</label>
                                <input id="card_name" type="text"
                                       class="input"
                                       placeholder="Jane Smith"
                                       autocomplete="cc-name">
                            </div>
                        </div>

                        {{-- Security note --}}
                        <div style="display:flex;align-items:center;gap:.5rem;margin-top:1.125rem;">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M6 1L2 3v3c0 2.2 1.7 4.3 4 5 2.3-.7 4-2.8 4-5V3L6 1z" stroke="var(--text-3)" stroke-width="1" stroke-linejoin="round"/>
                            </svg>
                            <span class="mono" style="font-size:.58rem;color:var(--text-3);letter-spacing:.06em;">
                                Secured by Stripe · 256-bit TLS encryption
                            </span>
                        </div>
                    </div>
                </div>

                {{-- ── Terms + submit ───────────────────────── --}}
                <div style="margin-top:1.75rem;">
                    <label class="check-wrap" style="margin-bottom:1.25rem;" id="terms-label">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span class="check-box" id="terms-box">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                <path d="M1 4l2 2 4-4" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="check-txt">
                            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn-primary" id="submit-btn">
                    Create free account
                </button>

            </form>

            <div class="form-footer">
                Already have an account? <a href="{{ route('login') }}">Sign in →</a>
            </div>

        </div>
    </main>

</div>

<script>
    // ── Theme ─────────────────────────────────────────────────
    const html  = document.documentElement;
    const saved = localStorage.getItem('cc-theme') || 'dark';
    html.dataset.theme = saved;
    document.getElementById('theme-toggle').addEventListener('click', () => {
        const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = next;
        localStorage.setItem('cc-theme', next);
    });

    // ── Password toggles ──────────────────────────────────────
    document.querySelectorAll('.toggle-vis').forEach(btn => {
        btn.addEventListener('click', () => {
            const input  = document.getElementById(btn.dataset.target);
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            btn.querySelector('.eye-open').style.display   = isText ? 'block' : 'none';
            btn.querySelector('.eye-closed').style.display = isText ? 'none'  : 'block';
        });
    });

    // ── Plan selection ────────────────────────────────────────
    const planCards    = document.querySelectorAll('.plan-card');
    const planInput    = document.getElementById('plan_value');
    const paySection   = document.getElementById('payment-section');
    const submitBtn    = document.getElementById('submit-btn');
    const trialPlan    = document.getElementById('trial-plan-name');

    const planLabels = { starter: 'Create free account', pro: 'Start 14-day free trial', studio: 'Start 14-day free trial' };

    function selectPlan(id) {
        planCards.forEach(card => {
            const sel = card.dataset.plan === id;
            card.classList.toggle('selected', sel);
            card.setAttribute('aria-checked', sel ? 'true' : 'false');
            const nameEl = card.querySelector('.plan-name');
            if (nameEl) nameEl.style.color = sel ? 'var(--cut)' : 'var(--text-3)';
        });

        planInput.value = id;

        if (id === 'starter') {
            paySection.classList.remove('visible');
        } else {
            paySection.classList.add('visible');
            trialPlan.textContent = id.charAt(0).toUpperCase() + id.slice(1);
        }

        submitBtn.textContent = planLabels[id] || 'Create account';
    }

    planCards.forEach(card => {
        card.addEventListener('click', () => selectPlan(card.dataset.plan));
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectPlan(card.dataset.plan); }
        });
    });

    // ── Card number formatting ─────────────────────────────────
    const cardInput   = document.getElementById('card_number');
    const cardDisplay = document.getElementById('card-display');

    cardInput.addEventListener('input', e => {
        let raw = e.target.value.replace(/\D/g, '').substring(0, 16);
        let fmt = raw.match(/.{1,4}/g)?.join('  ') || raw;
        e.target.value = fmt;

        // Update visual display
        const padded = (raw + '################').substring(0, 16);
        const masked = padded.match(/.{1,4}/g).join(' \u00a0');
        cardDisplay.textContent = masked.replace(/#/g, '•');
    });

    // ── Expiry formatting ──────────────────────────────────────
    document.getElementById('card_expiry').addEventListener('input', e => {
        let raw = e.target.value.replace(/\D/g, '').substring(0, 4);
        if (raw.length >= 3) raw = raw.substring(0, 2) + ' / ' + raw.substring(2);
        else if (raw.length === 2) raw = raw + ' / ';
        e.target.value = raw;
    });

    // ── CVC: digits only ──────────────────────────────────────
    document.getElementById('card_cvc').addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    });

    // ── Terms checkbox ────────────────────────────────────────
    const termsCb  = document.getElementById('terms');
    const termsBox = document.getElementById('terms-box');
    termsCb.addEventListener('change', () => {
        termsBox.style.background   = termsCb.checked ? 'var(--cut)' : 'var(--surface)';
        termsBox.style.borderColor  = termsCb.checked ? 'var(--cut)' : '';
    });
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en" data-theme="dark" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CutContour — Automated Cut Path Generation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        /* ── THEME TOKENS ───────────────────────────────────────── */
        :root {
            --cut:        #ec008c;
            --cut-hover:  #d4007e;
            --cut-10:     rgba(236,0,140,.10);
            --cut-20:     rgba(236,0,140,.20);
            --cut-06:     rgba(236,0,140,.06);
            --green:      #22c55e;
            --transition: color .2s, background .2s, border-color .2s, opacity .2s;
        }

        [data-theme="dark"] {
            --bg:        #0e0e0d;
            --bg-2:      #161614;
            --bg-3:      #1e1d1b;
            --surface:   #232220;
            --surface-2: #2c2b28;
            --border:    rgba(255,255,255,.08);
            --border-2:  rgba(255,255,255,.14);
            --text:      #f0ede7;
            --text-2:    #9a9690;
            --text-3:    rgba(255,255,255,.28);
            --text-inv:  #111110;
            --dot-color: rgba(255,255,255,.07);
            --paper-bg:  #f5f4f0;
            --paper-text:#111110;
        }
        [data-theme="light"] {
            --bg:        #f5f4f0;
            --bg-2:      #ffffff;
            --bg-3:      #eceae4;
            --surface:   #ffffff;
            --surface-2: #f0ede7;
            --border:    rgba(0,0,0,.09);
            --border-2:  rgba(0,0,0,.16);
            --text:      #111110;
            --text-2:    #706d66;
            --text-3:    rgba(0,0,0,.32);
            --text-inv:  #f5f4f0;
            --dot-color: rgba(0,0,0,.07);
            --paper-bg:  #111110;
            --paper-text:#f5f4f0;
        }

        /* ── RESET / BASE ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html   { font-family: 'DM Sans', sans-serif; }
        body   { background: var(--bg); color: var(--text); overflow-x: hidden;
                 transition: background .35s, color .35s; }
        a      { text-decoration: none; }

        /* ── TYPOGRAPHY ─────────────────────────────────────────── */
        .serif { font-family: 'Cormorant Garamond', Georgia, serif; }
        .mono  { font-family: 'Space Mono', monospace; }

        .section-label {
            font-family: 'Space Mono', monospace;
            font-size: .63rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--cut);
        }

        /* ── DOT GRID ───────────────────────────────────────────── */
        .dots {
            background-image: radial-gradient(circle, var(--dot-color) 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }

        /* ── REGISTRATION MARK ──────────────────────────────────── */
        .reg {
            position: absolute; width: 22px; height: 22px;
            opacity: .18; pointer-events: none;
        }
        .reg::before, .reg::after {
            content: ''; position: absolute; background: var(--text);
        }
        .reg::before { width: 1px; height: 100%; left: 50%; transform: translateX(-50%); }
        .reg::after  { height: 1px; width: 100%; top:  50%; transform: translateY(-50%); }

        /* ── NAV ────────────────────────────────────────────────── */
        #nav {
            position: fixed; inset: 0 0 auto 0; z-index: 100;
            padding: 0 1.5rem;
            height: 60px;
            display: flex; align-items: center;
            transition: background .3s, border-color .3s;
        }
        #nav.scrolled {
            background: rgba(14,14,13,.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
        }
        [data-theme="light"] #nav.scrolled {
            background: rgba(245,244,240,.9);
        }
        .nav-inner {
            width: 100%; max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
        }
        .nav-logo { display: flex; align-items: center; gap: .625rem; }
        .nav-logo-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.15rem; font-weight: 500; color: var(--text);
            letter-spacing: -.01em;
        }
        .nav-links {
            display: flex; align-items: center; gap: 2rem;
        }
        .nav-links a {
            font-size: .875rem; color: var(--text-2);
            transition: color .2s;
        }
        .nav-links a:hover { color: var(--text); }
        .nav-right { display: flex; align-items: center; gap: .625rem; }

        /* Mobile nav */
        .nav-links { display: none; }
        @media (min-width: 768px) { .nav-links { display: flex; } }

        #mobile-menu-btn {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 4px;
            background: transparent; border: 1px solid var(--border);
            cursor: pointer; color: var(--text-2);
            transition: var(--transition);
        }
        #mobile-menu-btn:hover { border-color: var(--border-2); color: var(--text); }
        @media (min-width: 768px) { #mobile-menu-btn { display: none; } }

        #mobile-menu {
            display: none; position: fixed;
            top: 60px; inset-inline: 0;
            background: var(--bg-2); border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem 1.25rem; z-index: 99;
            flex-direction: column; gap: .125rem;
        }
        #mobile-menu.open { display: flex; }
        #mobile-menu a {
            padding: .75rem .5rem;
            font-size: .95rem; color: var(--text-2);
            border-bottom: 1px solid var(--border);
            transition: color .2s;
        }
        #mobile-menu a:last-child { border-bottom: none; }
        #mobile-menu a:hover { color: var(--text); }

        /* ── THEME TOGGLE ───────────────────────────────────────── */
        #theme-toggle {
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 4px; border: 1px solid var(--border);
            background: transparent; cursor: pointer; color: var(--text-2);
            transition: var(--transition);
        }
        #theme-toggle:hover { border-color: var(--border-2); color: var(--cut); }
        [data-theme="dark"]  .icon-moon { display: none; }
        [data-theme="light"] .icon-sun  { display: none; }

        /* ── BUTTONS ────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: .6875rem 1.5rem;
            border-radius: 3px; font-family: 'DM Sans', sans-serif;
            font-size: .875rem; font-weight: 500; letter-spacing: .015em;
            cursor: pointer; border: none; text-decoration: none;
            transition: background .2s, border-color .2s, color .2s, transform .15s, opacity .2s;
            white-space: nowrap;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: var(--cut); color: #fff; position: relative; overflow: hidden; }
        .btn-primary:hover { background: var(--cut-hover); transform: translateY(-1px); }
        .btn-ghost {
            background: transparent; color: var(--text-2);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { border-color: var(--border-2); color: var(--text); }
        .btn-sm { padding: .5rem 1rem; font-size: .8125rem; }

        /* ── HERO ────────────────────────────────────────────────── */
        #hero {
            min-height: 100svh; padding-top: 60px;
            display: flex; align-items: center;
            position: relative; overflow: hidden;
        }
        .hero-grid {
            max-width: 1200px; margin: 0 auto; padding: 4rem 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 3rem;
            align-items: center;
        }
        @media (min-width: 1024px) {
            .hero-grid {
                grid-template-columns: 1fr 1fr;
                gap: 5rem;
                padding: 5rem 1.5rem;
            }
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 1.5rem;
            animation: fade-up .7s ease .1s both;
        }
        .hero-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: clamp(3rem, 7vw, 5.5rem);
            font-weight: 300;
            line-height: 1.04;
            color: var(--text);
            margin-bottom: 1.5rem;
            animation: fade-up .8s cubic-bezier(.16,1,.3,1) .15s both;
        }
        .hero-title em { font-style: italic; color: var(--cut); }
        .hero-sub {
            font-size: 1rem; line-height: 1.75;
            color: var(--text-2); max-width: 440px;
            margin-bottom: 2.25rem;
            animation: fade-up .8s cubic-bezier(.16,1,.3,1) .25s both;
        }
        .hero-ctas {
            display: flex; flex-wrap: wrap; gap: .75rem;
            margin-bottom: 2.75rem;
            animation: fade-up .8s cubic-bezier(.16,1,.3,1) .35s both;
        }
        .hero-specs {
            display: flex; flex-wrap: wrap; gap: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            animation: fade-up .8s cubic-bezier(.16,1,.3,1) .45s both;
        }
        .spec-item {}
        .spec-label {
            font-family: 'Space Mono', monospace;
            font-size: .58rem; letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-3); margin-bottom: .3rem;
        }
        .spec-val {
            font-family: 'Space Mono', monospace;
            font-size: .72rem; color: var(--text-2);
            display: flex; align-items: center; gap: .4rem;
        }
        .swatch {
            display: inline-block; width: 9px; height: 9px;
            border-radius: 2px; background: var(--cut); flex-shrink: 0;
        }

        /* ── CANVAS MOCKUP ──────────────────────────────────────── */
        .canvas-wrap {
            display: flex; justify-content: center;
            position: relative;
            animation: fade-up .9s cubic-bezier(.16,1,.3,1) .3s both;
        }
        @media (min-width: 1024px) {
            .canvas-wrap { justify-content: flex-end; }
        }
        .canvas-frame {
            width: min(340px, 100%);
            background: var(--bg-3);
            border: 1px solid var(--border-2);
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 32px 72px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.04);
            animation: float 6s ease-in-out 4.5s infinite;
        }
        [data-theme="light"] .canvas-frame {
            box-shadow: 0 24px 60px rgba(0,0,0,.18), 0 0 0 1px rgba(0,0,0,.08);
        }
        .canvas-toolbar {
            height: 40px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 .875rem;
        }
        .traffic-lights { display: flex; gap: 5px; }
        .tl { width: 10px; height: 10px; border-radius: 50%; }
        .tl-r { background: #ff5f57; }
        .tl-y { background: #febc2e; }
        .tl-g { background: #28c840; }
        .toolbar-filename {
            font-family: 'Space Mono', monospace;
            font-size: .58rem; color: var(--text-3); letter-spacing: .06em;
        }
        .toolbar-status {
            display: flex; align-items: center; gap: 5px;
            font-family: 'Space Mono', monospace;
            font-size: .58rem; color: var(--text-3);
        }
        .canvas-body {
            padding: 1.25rem;
            display: flex; align-items: center; justify-content: center;
            min-height: 280px; position: relative;
        }
        .ruler-x {
            position: absolute; bottom: .5rem; left: 50%;
            transform: translateX(-50%);
            font-family: 'Space Mono', monospace;
            font-size: .52rem; color: var(--text-3); letter-spacing: .05em;
        }
        .ruler-y {
            position: absolute; left: .4rem; top: 50%;
            transform: translateY(-50%) rotate(-90deg);
            font-family: 'Space Mono', monospace;
            font-size: .52rem; color: var(--text-3); letter-spacing: .05em;
        }
        .artboard {
            width: 200px; height: 252px;
            background: #fff; border-radius: 3px;
            position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,.45);
            display: flex; align-items: center; justify-content: center;
        }
        [data-theme="light"] .artboard {
            box-shadow: 0 4px 20px rgba(0,0,0,.15);
            border: 1px solid rgba(0,0,0,.08);
        }
        .artwork-ph {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            opacity: .35;
        }
        .art-circle { width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg,#d0d0d0,#b0b0b0); }
        .art-bar-1  { width: 52px; height: 9px; border-radius: 2px; background: #c0c0c0; }
        .art-bar-2  { width: 36px; height: 7px; border-radius: 2px; background: #d0d0d0; }

        /* animated cut path */
        .cut-svg {
            position: absolute; inset: 0; width: 100%; height: 100%;
            overflow: visible;
        }
        .cut-path {
            stroke-dasharray: 1260;
            stroke-dashoffset: 1260;
            animation: trace 3.6s cubic-bezier(.4,0,.2,1) .9s forwards;
        }
        .cut-label { animation: fade-in .4s ease 4s both; }

        /* floating chips */
        .chip {
            position: absolute;
            background: var(--surface-2);
            border: 1px solid var(--border-2);
            border-radius: 4px;
            padding: .4rem .8rem;
            display: flex; align-items: center; gap: .5rem;
            font-family: 'Space Mono', monospace;
            font-size: .6rem; color: var(--text-2);
            box-shadow: 0 8px 24px rgba(0,0,0,.4);
            white-space: nowrap;
        }
        [data-theme="light"] .chip {
            box-shadow: 0 4px 14px rgba(0,0,0,.12);
        }
        .chip-1 {
            top: -14px; right: -18px;
            border-color: var(--cut-20);
            animation: fade-up .5s ease 4.2s both;
        }
        .chip-2 {
            bottom: -14px; left: -18px;
            animation: fade-up .5s ease 4.6s both; opacity: 0;
        }
        @media (max-width: 400px) {
            .chip-1, .chip-2 { display: none; }
        }

        /* ── STATS STRIP ─────────────────────────────────────────── */
        #stats {
            border-top: 1.5px dashed var(--cut-20);
            border-bottom: 1.5px dashed var(--cut-20);
        }
        .stats-grid {
            max-width: 1200px; margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        @media (min-width: 640px) {
            .stats-grid { grid-template-columns: repeat(4,1fr); }
        }
        .stat-cell {
            padding: 1.5rem 1rem; text-align: center;
            border-right: 1px solid var(--border);
        }
        .stat-cell:last-child { border-right: none; }
        @media (max-width: 639px) {
            .stat-cell:nth-child(2n) { border-right: none; }
            .stat-cell:nth-child(1),
            .stat-cell:nth-child(2) { border-bottom: 1px solid var(--border); }
        }
        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.25rem; font-weight: 500;
            color: var(--text); line-height: 1.1; margin-bottom: .3rem;
        }
        .stat-lbl {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .08em; text-transform: uppercase;
            color: var(--text-3);
        }

        /* ── SECTION BASE ─────────────────────────────────────────── */
        .section { padding: 5rem 1.5rem; }
        @media (min-width: 768px) { .section { padding: 7rem 1.5rem; } }
        .section-inner { max-width: 1200px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 4rem; }
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 300; line-height: 1.18;
            color: var(--text); margin-top: .75rem;
        }
        .section-title em { font-style: italic; color: var(--cut); }
        .section-body {
            font-size: .9375rem; line-height: 1.75; color: var(--text-2);
            max-width: 500px; margin: .875rem auto 0;
        }

        /* ── ALT SECTION (inverted bg) ──────────────────────────── */
        .section-alt { background: var(--bg-2); }

        /* ── STEP CARDS ──────────────────────────────────────────── */
        .steps-grid {
            display: grid; gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 640px)  { .steps-grid { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 1024px) { .steps-grid { grid-template-columns: repeat(3,1fr); } }

        .step-card {
            border: 1px solid var(--border);
            border-radius: 6px; padding: 1.75rem;
            background: var(--surface);
            position: relative; overflow: hidden;
            transition: border-color .3s, box-shadow .3s;
        }
        .step-card:hover {
            border-color: var(--cut-20);
            box-shadow: 0 0 0 3px var(--cut-06);
        }
        .step-n {
            font-family: 'Cormorant Garamond', serif;
            font-size: 5rem; font-weight: 300;
            line-height: 1; color: var(--cut-10);
            position: absolute; top: 1rem; right: 1.25rem;
            transition: color .3s;
        }
        .step-card:hover .step-n { color: var(--cut-20); }
        .step-icon { margin-bottom: 1.25rem; }
        .step-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem; font-weight: 400;
            color: var(--text); margin-bottom: .75rem;
        }
        .step-body { font-size: .875rem; line-height: 1.7; color: var(--text-2); }
        .step-tags { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: 1.125rem; }
        .tag {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .07em;
            padding: .3rem .6rem; border-radius: 2px;
            background: var(--bg-3); color: var(--text-3);
            border: 1px solid var(--border);
        }
        .step-note {
            display: flex; align-items: center; gap: .375rem;
            margin-top: 1.125rem;
            font-family: 'Space Mono', monospace;
            font-size: .6rem; color: var(--text-3);
        }

        /* ── PIPELINE SECTION ────────────────────────────────────── */
        .pipeline-layout {
            display: grid; gap: 3.5rem;
            grid-template-columns: 1fr;
            align-items: start;
        }
        @media (min-width: 1024px) {
            .pipeline-layout { grid-template-columns: 1fr 1fr; gap: 5rem; }
        }

        .pipeline-cols {
            display: grid; gap: .875rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 480px) { .pipeline-cols { grid-template-columns: 1fr 1fr; } }

        .pipe-card {
            border: 1px dashed var(--border-2);
            border-radius: 4px; padding: 1.25rem;
            background: var(--surface);
        }
        .pipe-card.ai-card { border-color: var(--cut-20); background: var(--cut-06); }
        .pipe-head {
            font-family: 'Space Mono', monospace;
            font-size: .62rem; letter-spacing: .09em; font-weight: 700;
            margin-bottom: .875rem;
        }
        .pipe-head.ai-head { color: var(--cut); }
        .pipe-step {
            display: flex; align-items: center; gap: .625rem;
            padding: .35rem 0;
            border-top: 1px solid var(--border);
            font-family: 'Space Mono', monospace;
            font-size: .66rem; color: var(--text-2);
        }
        .pipe-step:first-of-type { border-top: none; }
        .pipe-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: var(--text-3); flex-shrink: 0;
        }
        .pipe-dot.ai { background: var(--cut); }
        .pipe-dot.ai-txt { color: var(--cut); }
        .pipe-foot {
            margin-top: .75rem; padding-top: .75rem;
            border-top: 1px solid var(--border);
            font-family: 'Space Mono', monospace;
            font-size: .6rem;
        }

        /* feature list on right */
        .feat-list { display: flex; flex-direction: column; gap: 0; }
        .feat-item {
            display: flex; gap: 1rem;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border);
        }
        .feat-item:last-child { border-bottom: none; }
        .feat-check {
            width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;
            border: 1.5px solid var(--cut); border-radius: 3px;
            display: flex; align-items: center; justify-content: center;
        }
        .feat-title { font-size: .875rem; font-weight: 500; color: var(--text); margin-bottom: .25rem; }
        .feat-body  { font-size: .8125rem; line-height: 1.65; color: var(--text-2); }

        /* ── SPOT COLOUR CALLOUT ────────────────────────────────── */
        #spot {
            border-top: 1.5px dashed var(--cut-20);
        }
        .spot-box {
            border: 1.5px dashed var(--cut-20);
            border-radius: 6px; padding: clamp(2rem,6vw,5rem);
            text-align: center;
            background: var(--cut-06);
            position: relative; overflow: hidden;
        }
        .spot-eyebrow {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1rem,2vw,1.35rem);
            font-style: italic; font-weight: 300;
            color: var(--text-3); margin-bottom: .625rem;
        }
        .spot-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.4rem,6vw,4.5rem);
            font-weight: 300; line-height: 1.12;
            color: var(--text); margin-bottom: 1.25rem;
        }
        .spot-title span { color: var(--cut); }
        .spot-sub {
            font-size: .9375rem; line-height: 1.75; color: var(--text-2);
            max-width: 480px; margin: 0 auto 2rem;
        }
        .chips-row {
            display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem;
        }
        .cmyk-chip {
            display: inline-flex; align-items: center; gap: .5rem;
            background: var(--cut-10); border: 1px solid var(--cut-20);
            border-radius: 3px; padding: .4rem .9rem;
            font-family: 'Space Mono', monospace; font-size: .68rem;
            color: var(--cut);
        }

        /* ── COMPAT ───────────────────────────────────────────────── */
        .compat-grid {
            display: grid; gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 640px)  { .compat-grid { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 1024px) { .compat-grid { grid-template-columns: repeat(3,1fr); } }

        .compat-card {
            border: 1px solid var(--border);
            border-radius: 6px; padding: 1.5rem;
            background: var(--surface); text-align: center;
            transition: border-color .2s, box-shadow .2s;
        }
        .compat-card:hover {
            border-color: var(--cut-20);
            box-shadow: 0 0 0 3px var(--cut-06);
        }
        .compat-version {
            font-family: 'Space Mono', monospace;
            font-size: .6rem; letter-spacing: .09em; text-transform: uppercase;
            color: var(--text-3); margin-bottom: .625rem;
        }
        .compat-name { font-weight: 500; color: var(--text); margin-bottom: .5rem; }
        .compat-note { font-size: .8125rem; line-height: 1.65; color: var(--text-2); }

        .formats-row {
            display: flex; flex-wrap: wrap; justify-content: center;
            gap: .5rem; margin-top: 3rem;
        }
        .format-tag {
            font-family: 'Space Mono', monospace; font-size: .65rem;
            letter-spacing: .07em; text-transform: uppercase;
            padding: .35rem .8rem; border-radius: 2px;
            border: 1px solid var(--border);
            background: var(--surface); color: var(--text-2);
        }

        /* ── PRICING ─────────────────────────────────────────────── */
        .pricing-grid {
            display: grid; gap: 1rem;
            grid-template-columns: 1fr;
            max-width: 960px; margin: 0 auto;
        }
        @media (min-width: 640px)  { .pricing-grid { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 1024px) { .pricing-grid { grid-template-columns: repeat(3,1fr); } }

        .pricing-card {
            border: 1px solid var(--border);
            border-radius: 6px; padding: 1.75rem;
            background: var(--surface);
            display: flex; flex-direction: column;
            transition: border-color .25s;
        }
        .pricing-card:not(.pricing-featured):hover { border-color: var(--border-2); }
        .pricing-featured {
            border-color: var(--cut);
            background: var(--cut-06);
        }
        .pricing-plan {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .pricing-plan-name {
            font-family: 'Space Mono', monospace; font-size: .63rem;
            letter-spacing: .1em; text-transform: uppercase;
        }
        .pricing-badge {
            font-family: 'Space Mono', monospace;
            font-size: .57rem; letter-spacing: .06em;
            background: var(--cut); color: #fff;
            padding: .25rem .5rem; border-radius: 2px;
        }
        .pricing-price {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.75rem; font-weight: 300;
            color: var(--text); line-height: 1; margin-bottom: .25rem;
        }
        .pricing-price small {
            font-size: 1.25rem; color: var(--text-2);
        }
        .pricing-period {
            font-size: .8125rem; color: var(--text-2); margin-bottom: 1.25rem;
        }
        .pricing-divider { border: none; border-top: 1.5px dashed var(--border); margin-bottom: 1.25rem; }
        .pricing-featured .pricing-divider { border-color: var(--cut-20); }
        .pricing-features {
            list-style: none; display: flex; flex-direction: column; gap: .625rem;
            margin-bottom: 1.5rem; flex: 1;
        }
        .pricing-features li {
            display: flex; gap: .5rem;
            font-size: .8125rem; color: var(--text-2); line-height: 1.5;
        }
        .pricing-features li span:first-child { color: var(--cut); flex-shrink: 0; }

        /* ── CTA ─────────────────────────────────────────────────── */
        #cta {
            border-top: 1.5px dashed var(--cut-20);
            text-align: center; position: relative; overflow: hidden;
        }
        .cta-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.2rem,5vw,4.2rem);
            font-weight: 300; line-height: 1.1;
            color: var(--text); margin-top: .75rem; margin-bottom: 1.125rem;
        }
        .cta-title em { font-style: italic; color: var(--cut); }
        .cta-sub {
            font-size: .9375rem; line-height: 1.75; color: var(--text-2);
            max-width: 400px; margin: 0 auto 2.25rem;
        }
        .cta-btns { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; }

        /* ── FOOTER ──────────────────────────────────────────────── */
        footer {
            border-top: 1px solid var(--border);
            padding: 2rem 1.5rem;
        }
        .footer-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; flex-direction: column;
            align-items: center; gap: 1rem; text-align: center;
        }
        @media (min-width: 768px) {
            .footer-inner {
                flex-direction: row; justify-content: space-between;
                text-align: left;
            }
        }
        .footer-logo { display: flex; align-items: center; gap: .5rem; }
        .footer-copy {
            font-family: 'Space Mono', monospace; font-size: .6rem;
            letter-spacing: .05em; color: var(--text-3);
        }

        /* ── ANIMATIONS ──────────────────────────────────────────── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fade-in {
            from { opacity: 0; } to { opacity: 1; }
        }
        @keyframes trace {
            0%   { stroke-dashoffset: 1260; opacity: 0; }
            4%   { opacity: 1; }
            80%  { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: 0; opacity: 1; }
        }
        @keyframes blink {
            0%,100% { opacity: 1; } 50% { opacity: .2; }
        }
        @keyframes float {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-8px); }
        }

        .status-dot {
            display: inline-block; width: 7px; height: 7px;
            border-radius: 50%; background: var(--green);
            animation: blink 2.5s ease-in-out infinite;
        }

        /* ── SCROLL REVEAL ───────────────────────────────────────── */
        .reveal {
            opacity: 0; transform: translateY(22px);
            transition: opacity .7s cubic-bezier(.16,1,.3,1),
                        transform .7s cubic-bezier(.16,1,.3,1);
        }
        .reveal.in { opacity: 1; transform: none; }
        .d1 { transition-delay: .1s; }
        .d2 { transition-delay: .2s; }
        .d3 { transition-delay: .3s; }
    </style>
</head>
<body>

{{-- ══ NAV ═══════════════════════════════════════════════════════════ --}}
<nav id="nav">
    <div class="nav-inner">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="nav-logo">
            <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
                <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                      stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c"/>
            </svg>
            <span class="nav-logo-text">CutContour</span>
        </a>

        {{-- Desktop links --}}
        <nav class="nav-links">
            <a href="#how-it-works">How it works</a>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
        </nav>

        {{-- Right controls --}}
        <div class="nav-right">
            {{-- Theme toggle --}}
            <button id="theme-toggle" aria-label="Toggle theme">
                {{-- Sun (show in dark mode) --}}
                <svg class="icon-sun" width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="3.5" stroke="currentColor" stroke-width="1.25"/>
                    <path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.6 3.4l-1.1 1.1M4.5 11.5l-1.1 1.1M12.6 12.6l-1.1-1.1M4.5 4.5L3.4 3.4"
                          stroke="currentColor" stroke-width="1.25" stroke-linecap="round"/>
                </svg>
                {{-- Moon (show in light mode) --}}
                <svg class="icon-moon" width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M13 9.5A6.5 6.5 0 015.5 2 6.5 6.5 0 1013 9.5z"
                          stroke="currentColor" stroke-width="1.25" stroke-linejoin="round"/>
                </svg>
            </button>

            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Open App</a>
            @else
                <a href="{{ route('login') }}"    class="btn btn-ghost   btn-sm">Sign in</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get started</a>
            @endauth

            {{-- Mobile menu btn --}}
            <button id="mobile-menu-btn" aria-label="Menu">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>
</nav>

{{-- Mobile menu --}}
<div id="mobile-menu">
    <a href="#how-it-works">How it works</a>
    <a href="#features">Features</a>
    <a href="#pricing">Pricing</a>
</div>


{{-- ══ HERO ═════════════════════════════════════════════════════════ --}}
<section id="hero" class="dots">

    {{-- reg marks (desktop only) --}}
    <div class="reg" style="top:72px;left:24px;"></div>
    <div class="reg" style="top:72px;right:24px;"></div>
    <div class="reg" style="bottom:24px;left:24px;"></div>
    <div class="reg" style="bottom:24px;right:24px;"></div>

    <div class="hero-grid">

        {{-- Copy --}}
        <div>
            <div class="hero-badge">
                <span class="status-dot"></span>
                Automated cut path generation
            </div>

            <h1 class="hero-title">
                Print-ready PDFs
                <em>with perfect cut paths.</em>
            </h1>

            <p class="hero-sub">
                Upload your artwork. We create an accurate CutContour vector path and export a layered PDF ready for RIP software — no Illustrator required.
            </p>

            <div class="hero-ctas">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Open your workspace →</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary">Start generating free</a>
                    <a href="#how-it-works"           class="btn btn-ghost">See how it works</a>
                @endauth
            </div>

            <div class="hero-specs">
                <div class="spec-item">
                    <div class="spec-label">Spot colour</div>
                    <div class="spec-val"><span class="swatch"></span>C:0 M:100 Y:0 K:0</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Output</div>
                    <div class="spec-val">Layered PDF / 300 DPI+</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Processing</div>
                    <div class="spec-val">&lt; 30 seconds</div>
                </div>
            </div>
        </div>

        {{-- Canvas mockup --}}
        <div class="canvas-wrap">
            <div style="position:relative;">
                <div class="canvas-frame">
                    {{-- Toolbar --}}
                    <div class="canvas-toolbar">
                        <div class="traffic-lights">
                            <span class="tl tl-r"></span>
                            <span class="tl tl-y"></span>
                            <span class="tl tl-g"></span>
                        </div>
                        <span class="toolbar-filename">artwork.pdf</span>
                        <div class="toolbar-status">
                            <span class="status-dot" style="width:5px;height:5px;"></span>
                            Ready to export
                        </div>
                    </div>

                    {{-- Canvas body --}}
                    <div class="canvas-body dots">
                        <span class="ruler-x">10.5 in</span>
                        <span class="ruler-y">14.0 in</span>

                        <div class="artboard">
                            {{-- Artwork placeholder --}}
                            <div class="artwork-ph">
                                <div class="art-circle"></div>
                                <div class="art-bar-1"></div>
                                <div class="art-bar-2"></div>
                            </div>

                            {{-- Animated CutContour path --}}
                            <svg class="cut-svg" viewBox="0 0 200 252">
                                <rect class="cut-path"
                                    x="8" y="8" width="184" height="236" rx="20"
                                    fill="none"
                                    stroke="#ec008c" stroke-width="2"
                                    stroke-dasharray="9 4.5"
                                    stroke-linecap="round"/>
                                <g class="cut-label">
                                    <rect x="4" y="222" width="112" height="20" rx="3"
                                          fill="#111110" opacity=".9"/>
                                    <rect x="8" y="226" width="9" height="9" rx="1.5" fill="#ec008c"/>
                                    <text x="21" y="235.5"
                                          fill="rgba(255,255,255,.75)"
                                          font-family="'Space Mono',monospace"
                                          font-size="6.5" letter-spacing=".04em">
                                        CutContour C:0 M:100 Y:0 K:0
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Floating chips --}}
                <div class="chip chip-1">
                    <span style="width:8px;height:8px;border-radius:2px;background:var(--cut);display:inline-block;flex-shrink:0;"></span>
                    Offset +0.125 in
                </div>
                <div class="chip chip-2">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <circle cx="6" cy="6" r="5" stroke="rgba(255,255,255,.25)" stroke-width="1"/>
                        <path d="M2.5 6l2 2 5-4" stroke="#22c55e" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    AI path complete
                </div>
            </div>
        </div>

    </div>

    <div style="position:absolute;inset-inline:0;bottom:0;height:5rem;background:linear-gradient(to bottom,transparent,var(--bg));pointer-events:none;"></div>
</section>


{{-- ══ STATS ════════════════════════════════════════════════════════ --}}
<section id="stats" style="background:var(--bg-2);">
    <div class="stats-grid">
        @foreach([
            ['100 MB', 'Max file size'],
            ['< 30s',  'Processing target'],
            ['300 DPI+','Min output quality'],
            ['90 days','File retention'],
        ] as [$n,$l])
        <div class="stat-cell">
            <div class="stat-num">{{ $n }}</div>
            <div class="stat-lbl">{{ $l }}</div>
        </div>
        @endforeach
    </div>
</section>


{{-- ══ HOW IT WORKS ════════════════════════════════════════════════ --}}
<section id="how-it-works" class="section">
    <div class="section-inner">

        <div class="section-header reveal">
            <div class="section-label">Process</div>
            <h2 class="section-title">
                Three steps to a<br><em>production-ready file.</em>
            </h2>
            <p class="section-body">
                No design software. No manual path tracing. Upload once, download a PDF your print shop can run immediately.
            </p>
        </div>

        <div class="steps-grid">

            {{-- Step 1 --}}
            <div class="step-card reveal">
                <span class="step-n">01</span>
                <div class="step-icon">
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <rect x="1" y="1" width="32" height="32" rx="6"
                              stroke="var(--cut-20)" stroke-width="1" stroke-dasharray="4 3"/>
                        <path d="M17 10v14M10 17l7-7 7 7"
                              stroke="#ec008c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="step-title">Upload your artwork</h3>
                <p class="step-body">Drag in your JPG, PNG, PDF, SVG, or AI file — up to 100 MB. We accept everything the print industry works with.</p>
                <div class="step-tags">
                    @foreach(['JPG','PNG','PDF','SVG','AI'] as $f)
                    <span class="tag">{{ $f }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="step-card reveal d1">
                <span class="step-n">02</span>
                <div class="step-icon">
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <circle cx="17" cy="17" r="15" stroke="var(--cut-20)" stroke-width="1" stroke-dasharray="4 3"/>
                        <path d="M10 17a7 7 0 1114 0 7 7 0 01-14 0" stroke="#ec008c" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M17 13v4l2.5 2.5" stroke="var(--text-3)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="step-title">AI detects the subject</h3>
                <p class="step-body">Confidence analysis decides the pipeline path. Clean files are vectorised immediately. Complex artwork routes through AI subject isolation first — with automatic fallback.</p>
                <div class="step-note">
                    <span style="width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;flex-shrink:0;"></span>
                    AI fallback on every job
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="step-card reveal d2">
                <span class="step-n">03</span>
                <div class="step-icon">
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <rect x="7" y="2" width="20" height="26" rx="3"
                              stroke="var(--cut-20)" stroke-width="1" stroke-dasharray="4 3"/>
                        <path d="M11 10h12M11 15h12M11 20h8" stroke="var(--text-3)" stroke-width="1.2" stroke-linecap="round"/>
                        <path d="M17 27v5M13 30l4 2 4-2" stroke="#ec008c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="step-title">Download the PDF</h3>
                <p class="step-body">A layered PDF — artwork on Layer 1, CutContour vector spot colour on Layer 2. Opens correctly in Illustrator, CorelDRAW, and all major RIP systems.</p>
                <div class="step-note" style="color:var(--cut);">
                    CMYK 0 · 100 · 0 · 0 / Spot layer
                </div>
            </div>

        </div>
    </div>
</section>


{{-- ══ PIPELINE ════════════════════════════════════════════════════ --}}
<section id="features" class="section section-alt">
    <div class="section-inner">
        <div class="pipeline-layout">

            {{-- Left: pipeline --}}
            <div class="reveal">
                <div class="section-label">Pipeline</div>
                <h2 class="section-title" style="margin-top:.75rem;">
                    Two paths.<br><em>One consistent output.</em>
                </h2>
                <p class="section-body" style="margin: .875rem 0 2.25rem; max-width:400px;">
                    A confidence analysis runs on every upload. Clean artwork is processed deterministically. Complex files route through AI subject isolation first.
                </p>

                <div class="pipeline-cols">

                    <div class="pipe-card">
                        <div class="pipe-head" style="color:var(--text-2);">Fast Path</div>
                        @foreach(['Upload','Preprocess','Edge detection','Potrace vectorise','CutContour layer','Export PDF'] as $s)
                        <div class="pipe-step"><span class="pipe-dot"></span>{{ $s }}</div>
                        @endforeach
                        <div class="pipe-foot" style="color:var(--green);">Clean images</div>
                    </div>

                    <div class="pipe-card ai-card">
                        <div class="pipe-head ai-head">AI-Enhanced Path</div>
                        @foreach(['Upload','Preprocess','AI subject isolation','Mask normalisation','Potrace vectorise','CutContour layer','Export PDF'] as $i => $s)
                        <div class="pipe-step">
                            <span class="pipe-dot {{ in_array($i,[2,3]) ? 'ai' : '' }}"></span>
                            <span style="{{ in_array($i,[2,3]) ? 'color:var(--cut);' : '' }}">{{ $s }}</span>
                        </div>
                        @endforeach
                        <div class="pipe-foot" style="color:var(--cut);">Complex artwork</div>
                    </div>

                </div>
            </div>

            {{-- Right: feature list --}}
            <div class="reveal d1">
                <div class="feat-list" style="margin-top:4.5rem;">
                    @foreach([
                        ['Always vectors',       'The CutContour path is never rasterised at any stage. Crisp geometry at any zoom level, guaranteed.'],
                        ['Graceful degradation', 'If AI times out or fails, every job falls back to deterministic edge detection. Nothing fails silently.'],
                        ['RIP-native spot colour','Named exactly "CutContour" with CMYK 0·100·0·0 — matching what RIP software expects, no manual setup.'],
                        ['90-day retention',     'Jobs stay available for 90 days so you can re-download without reprocessing. Automatic cleanup keeps storage tidy.'],
                    ] as [$title,$desc])
                    <div class="feat-item">
                        <div class="feat-check">
                            <svg width="9" height="9" viewBox="0 0 9 9" fill="none">
                                <path d="M1.5 4.5l2 2 4-4" stroke="#ec008c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <div class="feat-title">{{ $title }}</div>
                            <div class="feat-body">{{ $desc }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</section>


{{-- ══ SPOT COLOUR CALLOUT ═════════════════════════════════════════ --}}
<section id="spot" class="section">
    <div class="section-inner">
        <div class="spot-box reveal">
            <div class="section-label" style="margin-bottom:.75rem;">Output Specification</div>
            <p class="spot-eyebrow">Every file exports with the same specification.</p>
            <h2 class="spot-title">
                <span>CutContour</span> spot layer.<br>
                CMYK <span class="mono" style="font-size:.62em;color:var(--text-3);">0 · 100 · 0 · 0</span>
            </h2>
            <p class="spot-sub">
                The spot colour is named exactly as RIP software expects. No renaming. No manual spot colour assignment in Illustrator. Load the PDF and cut.
            </p>
            <div class="chips-row">
                @foreach(['CutContour','Overprint: Off','Layer 2 / Top','Vector — never rasterised'] as $c)
                <span class="cmyk-chip">
                    @if($loop->first)<span style="width:10px;height:10px;border-radius:2px;background:var(--cut);display:inline-block;"></span>@endif
                    {{ $c }}
                </span>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ══ COMPATIBILITY ════════════════════════════════════════════════ --}}
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-header reveal">
            <div class="section-label">Compatibility</div>
            <h2 class="section-title">Opens correctly, everywhere.</h2>
            <p class="section-body">Tested against the tools print shops actually use.</p>
        </div>

        <div class="compat-grid">
            @foreach([
                ['Adobe Illustrator','CC and later',    'Spot colour preserved on import. Layers intact.'],
                ['CorelDRAW',        'X7 and later',    'CutContour layer recognised immediately by the RIP bridge.'],
                ['RIP Software',     'EFI · Caldera · Onyx','Spot colour name matches RIP conventions out of the box.'],
            ] as $i => [$app,$ver,$note])
            <div class="compat-card reveal" style="transition-delay:{{ $i * .1 }}s;">
                <div class="compat-version">{{ $ver }}</div>
                <div class="compat-name">{{ $app }}</div>
                <div class="compat-note">{{ $note }}</div>
            </div>
            @endforeach
        </div>

        <div class="formats-row reveal">
            @foreach(['JPG','JPEG','PNG','SVG','PDF','AI','Up to 100 MB'] as $f)
            <span class="format-tag">{{ $f }}</span>
            @endforeach
        </div>
    </div>
</section>


{{-- ══ PRICING ═════════════════════════════════════════════════════ --}}
<section id="pricing" class="section">
    <div class="section-inner">
        <div class="section-header reveal">
            <div class="section-label">Pricing</div>
            <h2 class="section-title">Simple, usage-based pricing.</h2>
        </div>

        <div class="pricing-grid">
            @php
            $plans = [
                ['Starter', 'Free', null, '10 jobs / month', false,
                 ['10 jobs per month','Up to 10 MB per file','Standard processing','30-day retention']],
                ['Pro', '$89', '/mo', '200 jobs / month', true,
                 ['200 jobs per month','Up to 100 MB per file','AI-enhanced processing','90-day retention','Priority queue']],
                ['Studio', '$250', '/mo', 'Unlimited jobs', false,
                 ['Unlimited jobs','Up to 100 MB per file','AI-enhanced processing','90-day retention','API access (soon)']],
            ];
            @endphp

            @foreach($plans as $i => [$plan,$price,$per,$sub,$feat,$items])
            <div class="pricing-card {{ $feat ? 'pricing-featured' : '' }} reveal" style="transition-delay:{{ $i * .1 }}s;">
                <div class="pricing-plan">
                    <span class="pricing-plan-name" style="color:{{ $feat ? 'var(--cut)' : 'var(--text-3)' }};">
                        {{ $plan }}
                    </span>
                    @if($feat)<span class="pricing-badge">Popular</span>@endif
                </div>
                <div class="pricing-price">
                    {{ $price }}<small>{{ $per }}</small>
                </div>
                <div class="pricing-period">{{ $sub }}</div>
                <hr class="pricing-divider">
                <ul class="pricing-features">
                    @foreach($items as $item)
                    <li><span>—</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}"
                   class="btn {{ $feat ? 'btn-primary' : 'btn-ghost' }} w-full" style="justify-content:center;">
                    {{ $price === 'Free' ? 'Get started' : ($feat ? 'Start free trial' : 'Contact us') }}
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ══ CTA ══════════════════════════════════════════════════════════ --}}
<section id="cta" class="section">
    <div class="reg" style="top:2rem;left:1.5rem;"></div>
    <div class="reg" style="top:2rem;right:1.5rem;"></div>
    <div class="reg" style="bottom:2rem;left:1.5rem;"></div>
    <div class="reg" style="bottom:2rem;right:1.5rem;"></div>
    <div class="section-inner" style="position:relative;">
        <div class="reveal">
            <div class="section-label">Get started</div>
            <h2 class="cta-title">
                Stop tracing paths manually.<br>
                <em>Let the machine do it.</em>
            </h2>
            <p class="cta-sub">Upload your first file for free. No credit card required. Cut-ready PDF in under 30 seconds.</p>
            <div class="cta-btns">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Open workspace →</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary">Create free account</a>
                    <a href="{{ route('login') }}"    class="btn btn-ghost">Sign in →</a>
                @endauth
            </div>
        </div>
    </div>
</section>


{{-- ══ FOOTER ═══════════════════════════════════════════════════════ --}}
<footer>
    <div class="footer-inner">
        <div class="footer-logo">
            <svg width="20" height="20" viewBox="0 0 26 26" fill="none">
                <rect x="1.5" y="1.5" width="23" height="23" rx="4" stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c"/>
            </svg>
            <span class="serif" style="font-size:.95rem;color:var(--text-3);font-weight:300;">CutContour</span>
        </div>
        <span class="footer-copy">Automated cut path generation for print production</span>
        <span class="footer-copy">&copy; {{ date('Y') }}</span>
    </div>
</footer>


<script>
    // ── Theme toggle ──────────────────────────────────────────────
    const html  = document.documentElement;
    const btn   = document.getElementById('theme-toggle');
    const saved = localStorage.getItem('cc-theme') || 'dark';
    html.dataset.theme = saved;

    btn.addEventListener('click', () => {
        const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = next;
        localStorage.setItem('cc-theme', next);
    });

    // ── Mobile menu ───────────────────────────────────────────────
    const menuBtn  = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    menuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
    });
    mobileMenu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => mobileMenu.classList.remove('open'));
    });

    // ── Nav scroll ────────────────────────────────────────────────
    const nav = document.getElementById('nav');
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // ── Scroll reveal ─────────────────────────────────────────────
    const io = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: .1 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>

</body>
</html>

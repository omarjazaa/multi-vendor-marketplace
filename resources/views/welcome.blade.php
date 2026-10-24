<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Vendor Marketplace — Interactive Platform Explorer</title>
    <meta name="description" content="Interactive frontend explorer for the Laravel 11 Multi-Vendor Marketplace with Domain-Driven Design, Design Patterns, and Polymorphic Gateway Refunds.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark light;
            --bg-base: #090d16;
            --bg-surface: #101726;
            --bg-card: rgba(22, 30, 49, 0.75);
            --bg-elevated: #1a243a;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(99, 102, 241, 0.3);
            
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --accent-emerald: #10b981;
            --accent-amber: #f59e0b;
            --accent-rose: #f43f5e;
            --accent-cyan: #06b6d4;
            --accent-purple: #a855f7;

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 18px;
            --radius-full: 9999px;

            --shadow-sm: 0 2px 4px rgba(0,0,0,0.2);
            --shadow-md: 0 8px 24px -4px rgba(0,0,0,0.3);
            --shadow-glow: 0 0 30px -5px var(--primary-glow);

            --transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        [data-theme="light"] {
            color-scheme: light;
            --bg-base: #f8fafc;
            --bg-surface: #ffffff;
            --bg-card: rgba(255, 255, 255, 0.85);
            --bg-elevated: #f1f5f9;
            --border-color: rgba(0, 0, 0, 0.08);
            --border-highlight: rgba(99, 102, 241, 0.4);
            
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-glow: rgba(79, 70, 229, 0.2);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        }

        /* Glassmorphism helpers */
        .glass-panel {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
        }

        /* Top Persona Switcher Bar */
        .persona-bar {
            background: rgba(16, 23, 38, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.6rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .persona-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .persona-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-right: 0.25rem;
        }

        .persona-btn {
            background: var(--bg-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: var(--transition);
        }

        .persona-btn:hover {
            color: var(--text-primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .persona-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 16px var(--primary-glow);
        }

        .theme-toggle-btn {
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 36px;
            height: 36px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle-btn:hover {
            transform: rotate(15deg);
        }

        /* App Header */
        .app-header {
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-surface);
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-primary);
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1.3rem;
            box-shadow: var(--shadow-glow);
        }

        .logo-title {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .logo-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: var(--radius-full);
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            border: 1px solid var(--border-highlight);
            font-weight: 600;
        }

        /* Main Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 2rem;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid transparent;
            padding: 0.6rem 1.1rem;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: var(--text-primary);
            background: var(--bg-elevated);
        }

        .tab-btn.active {
            color: #ffffff;
            background: var(--primary);
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        .badge-count {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 0.75rem;
            padding: 0.1rem 0.45rem;
            border-radius: var(--radius-full);
        }

        /* Content Container */
        .main-container {
            flex: 1;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .view-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .view-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Banner Card */
        .hero-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15)), var(--bg-surface);
            border: 1px solid var(--border-highlight);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--primary-glow), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        [data-theme="light"] .hero-title {
            background: linear-gradient(135deg, #0f172a, #334155);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            color: var(--text-secondary);
            font-size: 0.95rem;
            max-width: 600px;
        }

        .day-tag {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-emerald);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.35rem 0.8rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.75rem;
        }

        /* Grid & Cards */
        .catalog-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .category-chips {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .category-chip {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.4rem 0.9rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .category-chip:hover {
            color: var(--text-primary);
            border-color: var(--primary);
        }

        .category-chip.active {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary);
            border-color: var(--primary);
            font-weight: 600;
        }

        .search-box {
            position: relative;
            min-width: 280px;
        }

        .search-input {
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.5rem 1rem 0.5rem 2.25rem;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 12px var(--primary-glow);
        }

        .search-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-highlight);
            box-shadow: var(--shadow-md);
        }

        .product-thumb {
            height: 180px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 3rem;
            position: relative;
            overflow: hidden;
        }

        .product-store-badge {
            position: absolute;
            top: 0.6rem;
            left: 0.6rem;
            background: rgba(16, 23, 38, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .product-stock-badge {
            position: absolute;
            bottom: 0.6rem;
            right: 0.6rem;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stock-good { background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald); }
        .stock-low { background: rgba(245, 158, 11, 0.2); color: var(--accent-amber); }

        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            line-height: 1.3;
        }

        .product-desc {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .product-price {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary);
            font-family: 'Outfit', sans-serif;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.85rem;
            color: var(--accent-amber);
            font-weight: 600;
        }

        .btn-add-cart {
            width: 100%;
            margin-top: 1rem;
            background: var(--bg-elevated);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.65rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-add-cart:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* Cart & Checkout Layout */
        .cart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .cart-grid { grid-template-columns: 1fr; }
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .cart-qty-btn {
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 28px;
            height: 28px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: bold;
        }

        .cart-summary-box {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .summary-row.total {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary);
            padding-top: 1rem;
            border-top: 1px dashed var(--border-color);
        }

        .coupon-input-group {
            display: flex;
            gap: 0.5rem;
        }

        .input-text {
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.55rem 0.85rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            outline: none;
            flex: 1;
        }

        .btn-action {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-action:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .payment-radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .payment-option {
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-option:hover, .payment-option.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }

        /* Order & Dispute Cards */
        .order-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            transition: var(--transition);
        }

        .order-card:hover {
            border-color: var(--border-highlight);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.25rem 0.7rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-pending { background: rgba(245, 158, 11, 0.2); color: var(--accent-amber); }
        .badge-paid { background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald); }
        .badge-shipped { background: rgba(6, 182, 212, 0.2); color: var(--accent-cyan); }
        .badge-delivered { background: rgba(99, 102, 241, 0.2); color: var(--primary); }
        .badge-refunded { background: rgba(244, 63, 94, 0.2); color: var(--accent-rose); }
        .badge-cancelled { background: rgba(148, 163, 184, 0.2); color: var(--text-muted); }

        /* Dialog / Modal Styling (modern-web-guidance compliant) */
        dialog {
            background: var(--bg-surface);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 520px;
            width: 90%;
            margin: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        dialog::backdrop {
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
        }

        /* Live API Drawer */
        .api-telemetry-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(16, 23, 38, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
            z-index: 900;
            transform: translateY(calc(100% - 40px));
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .api-telemetry-drawer.open {
            transform: translateY(0);
        }

        .drawer-handle {
            height: 40px;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .drawer-content {
            height: 260px;
            overflow-y: auto;
            padding: 1rem 1.5rem;
            font-family: monospace;
            font-size: 0.8rem;
        }

        .log-entry {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.4rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .log-method {
            font-weight: bold;
            padding: 0.15rem 0.4rem;
            border-radius: var(--radius-sm);
        }
        .method-GET { color: var(--accent-emerald); background: rgba(16, 185, 129, 0.15); }
        .method-POST { color: var(--primary); background: rgba(99, 102, 241, 0.15); }
        .method-PATCH { color: var(--accent-amber); background: rgba(245, 158, 11, 0.15); }
        .method-DELETE { color: var(--accent-rose); background: rgba(244, 63, 94, 0.15); }

        .toast-container {
            position: fixed;
            top: 75px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }

        .toast {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            animation: slideIn 0.3s ease;
            pointer-events: auto;
            max-width: 380px;
        }

        .toast-success { border-color: var(--accent-emerald); color: var(--accent-emerald); }
        .toast-error { border-color: var(--accent-rose); color: var(--accent-rose); }

        /* Tracking Timeline Styles */
        .timeline-container {
            margin: 1.25rem 0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
        }

        .timeline-step {
            display: flex;
            gap: 1rem;
            position: relative;
        }

        .timeline-step::before {
            content: '';
            position: absolute;
            left: 17px;
            top: 36px;
            bottom: -16px;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-step:last-child::before {
            display: none;
        }

        .timeline-step.completed::before {
            background: var(--accent-emerald);
        }

        .timeline-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-elevated);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            z-index: 1;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .timeline-step.completed .timeline-icon-box {
            border-color: var(--accent-emerald);
            background: rgba(16, 185, 129, 0.15);
        }

        .timeline-step.current .timeline-icon-box {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.2);
            box-shadow: 0 0 12px var(--primary-glow);
        }

        /* Notifications Bell & Drawer (Day 30 Feature) */
        .notification-bell-btn {
            position: relative;
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 36px;
            height: 36px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.05rem;
        }

        .notification-bell-btn:hover {
            border-color: var(--primary);
            box-shadow: 0 0 12px var(--primary-glow);
            transform: translateY(-1px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--accent-rose);
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 2px 6px rgba(244, 63, 94, 0.4);
        }

        .notif-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2500;
            display: none;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 1.5rem;
            animation: fadeIn 0.2s ease;
        }

        .notif-modal-overlay.open {
            display: flex;
        }

        .notif-drawer {
            width: 100%;
            max-width: 440px;
            height: calc(100vh - 3rem);
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideInRight {
            from { transform: translateX(30px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .notif-header {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-elevated);
        }

        .notif-filter-bar {
            padding: 0.6rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
            background: var(--bg-surface);
        }

        .notif-filter-chip {
            background: var(--bg-elevated);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.78rem;
            padding: 0.3rem 0.75rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition);
        }

        .notif-filter-chip.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .notif-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .notif-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.85rem 1rem;
            transition: var(--transition);
            position: relative;
        }

        .notif-card.unread {
            border-left: 3px solid var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }

        .notif-card:hover {
            border-color: var(--primary);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- Sticky Top Persona Switcher -->
    <div class="persona-bar">
        <div class="persona-group">
            <span class="persona-label">⚡ Quick Persona Switch:</span>
            <button class="persona-btn active" id="btn-persona-guest" onclick="switchPersona('guest')">
                <span>👤</span> Guest Explorer
            </button>
            <button class="persona-btn" id="btn-persona-customer" onclick="switchPersona('customer')">
                <span>🛒</span> Customer (Sarah)
            </button>
            <button class="persona-btn" id="btn-persona-vendor" onclick="switchPersona('vendor')">
                <span>🏪</span> Vendor (Apex Tech)
            </button>
            <button class="persona-btn" id="btn-persona-admin" onclick="switchPersona('admin')">
                <span>🛡️</span> Admin (Omar)
            </button>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div id="auth-status-pill" style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-amber);" id="auth-dot"></span>
                <span id="auth-user-text">Browsing as Guest</span>
            </div>
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                <span id="theme-icon">🌙</span>
            </button>
        </div>
    </div>

    <!-- App Header -->
    <header class="app-header">
        <a href="#" class="logo-wrap">
            <div class="logo-icon">M</div>
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="logo-title">Multi-Vendor Marketplace</span>
                    <span class="logo-badge">Laravel 11 • DDD</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    Day 30: In-App Notification Center & Architecture Refinement
                </div>
            </div>
        </a>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button class="notification-bell-btn" onclick="toggleNotificationsModal()" title="View In-App Notifications" id="notification-bell-btn">
                <span>🔔</span>
                <span class="notification-badge" id="notification-badge-count" style="display: none;">0</span>
            </button>
            <button class="btn-action" style="background: rgba(16, 185, 129, 0.15); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.75rem; padding: 0.35rem 0.65rem;" onclick="checkSystemHealth()" title="Audit real-time Docker & Service Telemetry">
                🩺 System Health
            </button>
            <div style="font-size: 0.85rem; color: var(--text-secondary); text-align: right;">
                <span style="color: var(--accent-emerald); font-weight: 700;">● 350 Tests Passing</span><br>
                <span style="font-size: 0.75rem;">Docker Production Stack</span>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="nav-tabs" role="tablist">
        <button class="tab-btn active" onclick="navigateTab('marketplace')" id="tab-btn-marketplace">
            <span>🛍️</span> Marketplace Catalog
        </button>
        <button class="tab-btn" onclick="navigateTab('cart')" id="tab-btn-cart">
            <span>🛒</span> Shopping Cart & Checkout
            <span class="badge-count" id="cart-count">0</span>
        </button>
        <button class="tab-btn" onclick="navigateTab('orders')" id="tab-btn-orders">
            <span>📦</span> My Orders & Disputes
        </button>
        <button class="tab-btn" onclick="navigateTab('vendor')" id="tab-btn-vendor">
            <span>🏪</span> Vendor Command Center
        </button>
        <button class="tab-btn" onclick="navigateTab('admin')" id="tab-btn-admin">
            <span>🛡️</span> Admin Portal (Day 27 Refunds)
            <span class="badge-count" style="background: var(--accent-rose);" id="dispute-count">1</span>
        </button>
    </nav>

    <!-- Main Views Area -->
    <main class="main-container">

        <!-- VIEW 1: MARKETPLACE CATALOG -->
        <section id="view-marketplace" class="view-section active">
            <div class="hero-banner">
                <div>
                    <span class="day-tag">✨ Day 27 Active Feature</span>
                    <h1 class="hero-title">Multi-Vendor E-Commerce Engine</h1>
                    <p class="hero-desc">
                        Explore nested categories, dynamic pricing engine with Decorator pipeline, 
                        and independent vendor product catalogs. Add items to cart to test multi-vendor split checkouts!
                    </p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Platform Architecture:</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--accent-cyan);">● Decorator Pattern Pricing</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--accent-emerald);">● Strategy + Factory Payments</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--accent-purple);">● Polymorphic Gateway Refunds</div>
                </div>
            </div>

            <div class="catalog-controls">
                <div class="category-chips" id="categories-container">
                    <button class="category-chip active" onclick="filterCategory(null)">All Categories</button>
                </div>

                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="search-input" placeholder="Search catalog products..." oninput="handleSearch(this.value)">
                </div>
            </div>

            <div class="products-grid" id="products-grid">
                <!-- Injected via JavaScript -->
            </div>
        </section>

        <!-- VIEW 2: CART & CHECKOUT -->
        <section id="view-cart" class="view-section">
            <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 1.5rem;">Shopping Cart & Dynamic Pricing</h2>
            
            <div class="cart-grid">
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-secondary);">Cart Items</h3>
                    <div id="cart-items-container">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Store</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart-table-body">
                                <!-- Injected via JavaScript -->
                            </tbody>
                        </table>
                        <div id="cart-empty-msg" style="padding: 3rem; text-align: center; color: var(--text-muted); display: none;">
                            Your shopping cart is currently empty. Head over to the Marketplace to add products!
                        </div>
                    </div>
                </div>

                <div class="cart-summary-box">
                    <h3 style="font-size: 1.2rem; font-weight: 700;">Order Calculation (Decorator)</h3>
                    
                    <div class="summary-row">
                        <span>Items Subtotal:</span>
                        <span id="summary-subtotal">$0.00</span>
                    </div>
                    <div class="summary-row" style="color: var(--accent-emerald);">
                        <span>Coupon Discount:</span>
                        <span id="summary-discount">-$0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Calculated Tax (5%):</span>
                        <span id="summary-tax">+$0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Estimated Total:</span>
                        <span id="summary-total" style="color: var(--primary);">$0.00</span>
                    </div>

                    <!-- Coupon Code Input -->
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.35rem; display: block;">Apply Discount Coupon:</label>
                        <div class="coupon-input-group">
                            <input type="text" id="coupon-code-input" class="input-text" placeholder="e.g. WELCOME10">
                            <button class="btn-action" style="padding: 0.5rem 0.9rem;" onclick="applyCoupon()">Apply</button>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                            <button onclick="setCoupon('WELCOME10')" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; cursor: pointer; text-decoration: underline;">Use WELCOME10 (10%)</button>
                            <button onclick="setCoupon('SUPER50')" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; cursor: pointer; text-decoration: underline;">Use SUPER50 ($50)</button>
                        </div>
                    </div>

                    <!-- Payment Strategy Selection -->
                    <div>
                        <label style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block;">Payment Gateway Strategy (Day 17):</label>
                        <div class="payment-radio-group">
                            <label class="payment-option selected" id="opt-card">
                                <input type="radio" name="payment_method" value="card" checked onchange="selectPaymentMethod('card')">
                                <div>
                                    <div style="font-weight: 600; font-size: 0.9rem;">💳 Credit / Debit Card</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Simulated gateway with atomic payment status settlement</div>
                                </div>
                            </label>
                            <label class="payment-option" id="opt-cod">
                                <input type="radio" name="payment_method" value="cod" onchange="selectPaymentMethod('cod')">
                                <div>
                                    <div style="font-weight: 600; font-size: 0.9rem;">💵 Cash on Delivery</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Order stays unpaid until courier accepts cash payment</div>
                                </div>
                            </label>
                            <label class="payment-option" id="opt-bank">
                                <input type="radio" name="payment_method" value="bank_transfer" onchange="selectPaymentMethod('bank_transfer')">
                                <div>
                                    <div style="font-weight: 600; font-size: 0.9rem;">🏦 Bank Transfer</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Issues bank reference awaiting manual confirmation</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button class="btn-action" style="width: 100%; padding: 0.9rem; font-size: 1rem;" onclick="processCheckout()" id="btn-checkout">
                        <span>🚀</span> Complete Multi-Vendor Checkout
                    </button>
                </div>
            </div>
        </section>

        <!-- VIEW 3: ORDERS & DISPUTES (Customer View) -->
        <section id="view-orders" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="font-size: 1.6rem; font-weight: 800;">My Orders & Dispute Center</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Track orders across vendor splits, review mock shipment tracking, or file disputes.</p>
                </div>
                <button class="btn-action" onclick="fetchCustomerOrders()" style="background: var(--bg-elevated); border: 1px solid var(--border-color); color: var(--text-primary);">
                    🔄 Refresh Orders
                </button>
            </div>

            <div id="customer-orders-list">
                <!-- Injected via JavaScript -->
            </div>
        </section>

        <!-- VIEW 4: VENDOR COMMAND CENTER -->
        <section id="view-vendor" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <h2 style="font-size: 1.6rem; font-weight: 800;">Apex Electronics Portal</h2>
                        <span class="status-badge badge-paid">Store Approved</span>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Manage inventory, fulfill orders with mock shipment carrier numbers, and inspect CQRS analytics.</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-action" onclick="queueVendorReport()">📊 Queue Sales Report (Async Job)</button>
                    <button class="btn-action" style="background: var(--bg-elevated); border: 1px solid var(--border-color); color: var(--text-primary);" onclick="fetchVendorDashboard()">🔄 Refresh</button>
                </div>
            </div>

            <!-- CQRS Metrics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div class="glass-panel" style="padding: 1.25rem;">
                    <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">TOTAL REVENUE</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent-emerald);" id="vendor-stat-revenue">$1,938.90</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">CQRS Read Projection</div>
                </div>
                <div class="glass-panel" style="padding: 1.25rem;">
                    <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">ACTIVE PRODUCTS</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="vendor-stat-products">3 Items</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Live Catalog Synced</div>
                </div>
                <div class="glass-panel" style="padding: 1.25rem;">
                    <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">ORDERS FULFILLED</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent-cyan);" id="vendor-stat-orders">4 Orders</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Mock Carrier Tracking</div>
                </div>
                <div class="glass-panel" style="padding: 1.25rem;">
                    <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">AVG PRODUCT RATING</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent-amber);">★ 4.77</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Verified buyer reviews</div>
                </div>
            </div>

            <!-- Vendor Orders Fulfilment -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">Orders Requiring Vendor Shipment (Day 21)</h3>
                <div id="vendor-orders-list">
                    <!-- Injected via JavaScript -->
                </div>
            </div>
        </section>

        <!-- VIEW 5: ADMIN & DISPUTE CENTER (Day 27 Focus!) -->
        <section id="view-admin" class="view-section">
            <div class="hero-banner" style="background: linear-gradient(135deg, rgba(244, 63, 94, 0.15), rgba(99, 102, 241, 0.15)); border-color: rgba(244, 63, 94, 0.3);">
                <div>
                    <span class="day-tag" style="background: rgba(244, 63, 94, 0.2); color: var(--accent-rose); border-color: rgba(244, 63, 94, 0.3);">
                        ⚡ Day 27 Feature Focus
                    </span>
                    <h2 class="hero-title">Admin Dispute Resolution & Polymorphic Refunds</h2>
                    <p class="hero-desc">
                        Manage customer disputes and trigger runtime refunds through the original payment gateway (Card, COD, Bank Transfer).
                        Use <strong>Force RefundedState Transition</strong> to override the order state machine for lost in-transit packages!
                    </p>
                </div>
                <button class="btn-action" style="background: var(--accent-rose);" onclick="fetchAdminDisputes()">
                    🔄 Refresh Disputes
                </button>
            </div>

            <!-- Disputes Queue -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                    <span>Active Customer Disputes</span>
                    <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">Real-time triage queue</span>
                </h3>

                <div id="admin-disputes-container">
                    <!-- Injected via JavaScript -->
                </div>
            </div>

            <!-- Global Order Refund Override Tool -->
            <div class="glass-panel" style="padding: 1.5rem;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem;">Direct Order Refund & State Override Tool</h3>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1.25rem;">
                    Administratively refund any order. Checking "Force Transition" allows refunding orders in <code>shipped</code> status by bypassing normal lifecycle constraints.
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: flex-end; max-width: 900px;">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.35rem;">Order ID to Refund:</label>
                        <input type="number" id="direct-refund-order-id" class="input-text" placeholder="e.g. 2" value="2">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.35rem;">Administrative Reason:</label>
                        <input type="text" id="direct-refund-reason" class="input-text" placeholder="e.g. Customer dispute confirmed by courier" value="Carrier confirmed package lost in transit">
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: var(--accent-amber); cursor: pointer;">
                            <input type="checkbox" id="direct-refund-force" checked>
                            Force RefundedState
                        </label>
                        <button class="btn-action" style="background: var(--accent-rose);" onclick="executeDirectRefund()">
                            💸 Execute Gateway Refund
                        </button>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Modal for Customer Dispute Submission -->
    <dialog id="dispute-modal">
        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;" id="modal-dispute-title">File an Order Dispute</h3>
        <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1rem;">
            Explain what went wrong with your order. An administrator will review your claim and process a polymorphic gateway refund if approved.
        </p>

        <input type="hidden" id="modal-dispute-order-id">
        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">Dispute Explanation:</label>
        <textarea id="modal-dispute-reason" class="input-text" style="width: 100%; height: 100px; resize: vertical; margin-bottom: 1.25rem;" placeholder="e.g. Package arrived with damaged components..."></textarea>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button class="btn-action" style="background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeDisputeModal()">Cancel</button>
            <button class="btn-action" onclick="submitCustomerDispute()">Submit Dispute</button>
        </div>
    </dialog>

    <!-- Modal for Shipment Tracking & Visual Timeline -->
    <dialog id="tracking-modal" style="max-width: 560px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div>
                <span class="day-tag" style="margin-bottom: 0.35rem; display: inline-block;">🚚 Carrier Tracking Telemetry</span>
                <h3 style="font-size: 1.3rem; margin: 0;" id="tracking-modal-title">Tracking Package</h3>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Tracking Number: <code id="tracking-modal-number" style="color: var(--accent-cyan); font-weight: bold;">TRK-...</code>
                </div>
            </div>
            <button class="theme-toggle-btn" style="width: 28px; height: 28px; font-size: 0.8rem;" onclick="closeTrackingModal()" title="Close">✕</button>
        </div>

        <div id="tracking-modal-content">
            <!-- Timeline injected via JS -->
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
            <button class="btn-action" style="background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeTrackingModal()">Close</button>
        </div>
    </dialog>

    <!-- Modal for Customer Product Review Submission -->
    <dialog id="review-modal">
        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;" id="modal-review-title">Leave Product Review</h3>
        <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1rem;">
            Verified Purchase: Rate and review this delivered product. Your review will be cached on the product catalog.
        </p>

        <input type="hidden" id="modal-review-product-id">
        <div style="margin-bottom: 1rem;">
            <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">Rating:</label>
            <select id="modal-review-rating" class="input-text" style="width: 100%;">
                <option value="5">⭐⭐⭐⭐⭐ (5 Stars - Excellent)</option>
                <option value="4">⭐⭐⭐⭐ (4 Stars - Good)</option>
                <option value="3">⭐⭐⭐ (3 Stars - Average)</option>
                <option value="2">⭐⭐ (2 Stars - Poor)</option>
                <option value="1">⭐ (1 Star - Terrible)</option>
            </select>
        </div>

        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">Review Comment:</label>
        <textarea id="modal-review-comment" class="input-text" style="width: 100%; height: 90px; resize: vertical; margin-bottom: 1.25rem;" placeholder="Write your honest feedback..."></textarea>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button class="btn-action" style="background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeReviewModal()">Cancel</button>
            <button class="btn-action" style="background: var(--accent-emerald);" onclick="submitCustomerReview()">Submit Review</button>
        </div>
    </dialog>

    <!-- Live API Telemetry Console (Bottom Drawer) -->
    <div class="api-telemetry-drawer" id="api-drawer">
        <div class="drawer-handle" onclick="toggleApiDrawer()">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--accent-emerald);"></span>
                <span>⚡ Live API Telemetry & Network Inspector</span>
                <span class="badge-count" style="background: var(--primary);" id="api-call-count">0</span>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Header: X-API-Version: v1</span>
                <span id="drawer-toggle-arrow">▲</span>
            </div>
        </div>
        <div class="drawer-content" id="api-log-stream">
            <div style="color: var(--text-muted); padding: 0.5rem 0;">Waiting for API activity... Click around the platform to observe live Sanctum requests!</div>
        </div>
    </div>

    <!-- Notification Center Modal / Drawer (Day 30) -->
    <div class="notif-modal-overlay" id="notif-modal-overlay" onclick="handleNotifOverlayClick(event)">
        <div class="notif-drawer">
            <div class="notif-header">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="font-size: 1.25rem;">🔔</span>
                    <div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Notification Center</h3>
                        <span style="font-size: 0.75rem; color: var(--text-muted);" id="notif-unread-status">No unread notifications</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button class="btn-action" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;" onclick="markAllNotificationsAsRead()" title="Mark all notifications as read">
                        ✓ Mark All Read
                    </button>
                    <button class="theme-toggle-btn" style="width: 28px; height: 28px; font-size: 0.8rem;" onclick="toggleNotificationsModal()" title="Close">
                        ✕
                    </button>
                </div>
            </div>

            <div class="notif-filter-bar">
                <button class="notif-filter-chip active" id="notif-filter-all" onclick="setNotifFilter('all')">
                    All
                </button>
                <button class="notif-filter-chip" id="notif-filter-unread" onclick="setNotifFilter('unread')">
                    Unread Only
                </button>
                <button class="btn-action" style="margin-left: auto; background: transparent; border: none; color: var(--primary); font-size: 0.75rem; padding: 0; text-decoration: underline;" onclick="refreshNotifications()">
                    🔄 Refresh
                </button>
            </div>

            <div class="notif-body" id="notif-stream">
                <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                    Please select a persona (Customer, Vendor, or Admin) to view your in-app notifications.
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        // State Management
        const state = {
            currentPersona: 'guest',
            token: null,
            user: null,
            cart: [],
            coupon: null,
            selectedPayment: 'card',
            products: [],
            categories: [],
            orders: [],
            notifications: [],
            unreadNotificationCount: 0,
            notificationFilter: 'all',
            apiCalls: 0
        };

        // Demo Personas Credentials
        const personas = {
            guest: { email: null, role: 'guest', name: 'Guest Explorer' },
            customer: { email: 'customer@marketplace.com', password: 'password', role: 'customer', name: 'Sarah Connor' },
            vendor: { email: 'tech@marketplace.com', password: 'password', role: 'vendor', name: 'Alex (Apex Tech)' },
            admin: { email: 'admin@marketplace.com', password: 'password', role: 'admin', name: 'Omar (Administrator)' }
        };

        // Initialize on load
        document.addEventListener('DOMContentLoaded', async () => {
            await fetchCategories();
            await fetchProducts();
            updateAuthStatusUI();
            logApi('INIT', '/api/v1/ready', 200, 'Marketplace Frontend Initialized');
        });

        // Theme Toggle (modern-web-guidance compliant)
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.getElementById('theme-icon').textContent = next === 'dark' ? '🌙' : '☀️';
        }

        // Persona Switching
        async function switchPersona(type) {
            document.querySelectorAll('.persona-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(`btn-persona-${type}`).classList.add('active');
            state.currentPersona = type;

            if (type === 'guest') {
                state.token = null;
                state.user = null;
                state.notifications = [];
                state.unreadNotificationCount = 0;
                updateNotificationBadge();
                renderNotifications();
                showToast('Switched to Guest Mode', 'success');
                updateAuthStatusUI();
                return;
            }

            const p = personas[type];
            try {
                const res = await callApi('/api/v1/auth/login', 'POST', {
                    email: p.email,
                    password: p.password
                });

                if (res.data && res.data.token) {
                    state.token = res.data.token;
                    state.user = res.data.user;
                    showToast(`Logged in as ${p.name}`, 'success');
                    updateAuthStatusUI();

                    fetchUnreadNotificationCount();
                    fetchNotifications();

                    if (type === 'customer') {
                        navigateTab('cart');
                        fetchCart();
                        fetchCustomerOrders();
                    } else if (type === 'vendor') {
                        navigateTab('vendor');
                        fetchVendorDashboard();
                    } else if (type === 'admin') {
                        navigateTab('admin');
                        fetchAdminDisputes();
                    }
                }
            } catch (err) {
                showToast(`Login failed: ${err.message}`, 'error');
            }
        }

        function updateAuthStatusUI() {
            const pill = document.getElementById('auth-user-text');
            const dot = document.getElementById('auth-dot');
            if (state.token && state.user) {
                pill.textContent = `${state.user.name} (${state.user.role.toUpperCase()})`;
                dot.style.background = 'var(--accent-emerald)';
            } else {
                pill.textContent = 'Browsing as Guest';
                dot.style.background = 'var(--accent-amber)';
            }
        }

        // Tab Navigation
        function navigateTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active'));

            document.getElementById(`tab-btn-${tabId}`).classList.add('active');
            document.getElementById(`view-${tabId}`).classList.add('active');

            if (tabId === 'cart') fetchCart();
            if (tabId === 'orders') fetchCustomerOrders();
            if (tabId === 'vendor') fetchVendorDashboard();
            if (tabId === 'admin') fetchAdminDisputes();
        }

        // Unified API Client
        async function callApi(url, method = 'GET', body = null) {
            const start = performance.now();
            const headers = {
                'Accept': 'application/vnd.marketplace.v1+json',
                'Content-Type': 'application/json'
            };

            if (state.token) {
                headers['Authorization'] = `Bearer ${state.token}`;
            }

            const opts = { method, headers };
            if (body && method !== 'GET') {
                opts.body = JSON.stringify(body);
            }

            try {
                const response = await fetch(url, opts);
                const duration = Math.round(performance.now() - start);
                const data = await response.json().catch(() => ({}));

                logApi(method, url, response.status, data.message || (data.errors ? JSON.stringify(data.errors) : 'OK'), duration);

                if (!response.ok) {
                    const msg = data.message || Object.values(data.errors || {})[0] || 'Request failed';
                    throw new Error(msg);
                }

                return data;
            } catch (error) {
                const duration = Math.round(performance.now() - start);
                logApi(method, url, 500, error.message, duration);
                throw error;
            }
        }

        // Fetch Categories
        async function fetchCategories() {
            try {
                const res = await callApi('/api/v1/categories');
                state.categories = res.data?.categories || [];
                renderCategories();
            } catch (err) {
                console.error(err);
            }
        }

        function renderCategories() {
            const c = document.getElementById('categories-container');
            let html = '<button class="category-chip active" onclick="filterCategory(null)">All Categories</button>';
            state.categories.forEach(cat => {
                html += `<button class="category-chip" onclick="filterCategory(${cat.id}, this)">${cat.name}</button>`;
            });
            c.innerHTML = html;
        }

        // Fetch Products
        async function fetchProducts(categoryId = null, search = '') {
            try {
                let url = '/api/v1/products?per_page=12';
                if (categoryId) url += `&category_id=${categoryId}`;
                if (search) url += `&search=${encodeURIComponent(search)}`;

                const res = await callApi(url);
                state.products = res.data?.products?.data || [];
                renderProducts();
            } catch (err) {
                console.error(err);
            }
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            if (!state.products.length) {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted);">No products found matching criteria.</div>';
                return;
            }

            grid.innerHTML = state.products.map(p => {
                const emojis = ['💻', '🎧', '⚡', '⌚', '🧥', '🎒', '📱', '📦'];
                const emoji = emojis[p.id % emojis.length];
                const stockQty = 25; // default
                return `
                    <article class="product-card">
                        <div class="product-thumb">
                            <span>${emoji}</span>
                            <span class="product-store-badge">${p.store?.name || 'Verified Store'}</span>
                            <span class="product-stock-badge stock-good">In Stock</span>
                        </div>
                        <h3 class="product-name">${p.name}</h3>
                        <p class="product-desc">${p.description || 'Premium quality guaranteed.'}</p>
                        <div class="product-meta">
                            <span class="product-price">$${parseFloat(p.base_price).toFixed(2)}</span>
                            <span class="product-rating">★ ${p.average_rating || '4.8'}</span>
                        </div>
                        <button class="btn-add-cart" onclick="addToCart(${p.id})">
                            <span>🛒</span> Add to Cart
                        </button>
                    </article>
                `;
            }).join('');
        }

        function filterCategory(catId, btn = null) {
            document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            else document.querySelector('.category-chip').classList.add('active');
            fetchProducts(catId, document.getElementById('search-input').value);
        }

        let searchDebounce;
        function handleSearch(val) {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                fetchProducts(null, val);
            }, 300);
        }

        // Cart Logic
        async function addToCart(productId) {
            if (!state.token) {
                showToast('Please switch to Customer Persona to use cart', 'error');
                switchPersona('customer');
                return;
            }

            try {
                await callApi('/api/v1/cart/items', 'POST', {
                    product_id: productId,
                    quantity: 1
                });
                showToast('Added item to cart!', 'success');
                fetchCart();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function fetchCart() {
            if (!state.token) return;
            try {
                const res = await callApi('/api/v1/cart');
                const items = res.data?.cart?.items || [];
                state.cart = items;
                document.getElementById('cart-count').textContent = items.reduce((acc, i) => acc + i.quantity, 0);
                renderCartTable(items, res.data?.pricing);
            } catch (err) {
                console.error(err);
            }
        }

        function renderCartTable(items, pricing) {
            const tbody = document.getElementById('cart-table-body');
            const emptyMsg = document.getElementById('cart-empty-msg');

            if (!items.length) {
                tbody.innerHTML = '';
                emptyMsg.style.display = 'block';
                document.getElementById('summary-subtotal').textContent = '$0.00';
                document.getElementById('summary-discount').textContent = '-$0.00';
                document.getElementById('summary-tax').textContent = '+$0.00';
                document.getElementById('summary-total').textContent = '$0.00';
                return;
            }

            emptyMsg.style.display = 'none';
            tbody.innerHTML = items.map(item => `
                <tr>
                    <td>
                        <div style="font-weight: 600;">${item.product?.name || 'Product'}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: PROD-${item.product_id}</div>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem; color: var(--primary);">${item.product?.store?.name || 'Vendor Store'}</span>
                    </td>
                    <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <button class="cart-qty-btn" onclick="updateCartItemQty(${item.id}, ${item.quantity - 1})">-</button>
                            <span style="font-weight: 600; min-width: 20px; text-align: center;">${item.quantity}</span>
                            <button class="cart-qty-btn" onclick="updateCartItemQty(${item.id}, ${item.quantity + 1})">+</button>
                        </div>
                    </td>
                    <td style="font-weight: 700;">$${(parseFloat(item.unit_price) * item.quantity).toFixed(2)}</td>
                    <td>
                        <button onclick="removeCartItem(${item.id})" style="background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 1rem;">🗑️</button>
                    </td>
                </tr>
            `).join('');

            // Update Pricing Decorators Summary
            if (pricing) {
                document.getElementById('summary-subtotal').textContent = `$${parseFloat(pricing.subtotal || 0).toFixed(2)}`;
                document.getElementById('summary-discount').textContent = `-$${parseFloat(pricing.discount || 0).toFixed(2)}`;
                document.getElementById('summary-tax').textContent = `+$${parseFloat(pricing.tax || 0).toFixed(2)}`;
                document.getElementById('summary-total').textContent = `$${parseFloat(pricing.total || 0).toFixed(2)}`;
            }
        }

        async function updateCartItemQty(itemId, qty) {
            if (qty < 1) {
                removeCartItem(itemId);
                return;
            }
            try {
                await callApi(`/api/v1/cart/items/${itemId}`, 'PUT', { quantity: qty });
                fetchCart();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function removeCartItem(itemId) {
            try {
                await callApi(`/api/v1/cart/items/${itemId}`, 'DELETE');
                showToast('Item removed from cart', 'success');
                fetchCart();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function setCoupon(code) {
            document.getElementById('coupon-code-input').value = code;
            applyCoupon();
        }

        function applyCoupon() {
            const code = document.getElementById('coupon-code-input').value.trim();
            state.coupon = code || null;
            showToast(`Coupon applied: ${code}`, 'success');
            fetchCart();
        }

        function selectPaymentMethod(method) {
            state.selectedPayment = method;
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            document.getElementById(`opt-${method}`).classList.add('selected');
        }

        async function processCheckout() {
            if (!state.token) {
                showToast('Please switch to Customer Persona', 'error');
                return;
            }
            if (!state.cart.length) {
                showToast('Cart is empty', 'error');
                return;
            }

            try {
                const res = await callApi('/api/v1/checkout', 'POST', {
                    payment_method: state.selectedPayment,
                    coupon_code: state.coupon
                });

                showToast('Checkout successful! Orders created.', 'success');
                fetchCart();
                fetchUnreadNotificationCount();
                fetchNotifications();
                navigateTab('orders');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Customer Orders & Disputes
        async function fetchCustomerOrders() {
            if (!state.token) return;
            try {
                const res = await callApi('/api/v1/orders');
                const orders = res.data?.orders?.data || [];
                renderCustomerOrders(orders);
            } catch (err) {
                console.error(err);
            }
        }

        function renderCustomerOrders(orders) {
            const c = document.getElementById('customer-orders-list');
            if (!orders.length) {
                c.innerHTML = '<div class="glass-panel" style="padding: 3rem; text-align: center; color: var(--text-muted);">No orders found for this account. Place your first order from the cart!</div>';
                return;
            }

            c.innerHTML = orders.map(o => {
                const statusClass = `badge-${o.status}`;
                const isDisputeEligible = ['paid', 'shipped', 'delivered'].includes(o.status);
                const items = o.items || [];

                return `
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <span style="font-weight: 700; font-size: 1.1rem;">Order #${o.id}</span>
                                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.5rem;">Method: ${o.payment_method?.toUpperCase()}</span>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <span class="status-badge ${statusClass}">${o.status}</span>
                                <span style="font-weight: 800; font-size: 1.1rem; color: var(--primary);">$${parseFloat(o.total_price).toFixed(2)}</span>
                            </div>
                        </div>

                        ${items.length > 0 ? `
                            <div style="margin: 0.75rem 0; font-size: 0.85rem; border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                                ${items.map(item => `
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                        <span>${item.name} × ${item.quantity}</span>
                                        ${o.status === 'delivered' ? `
                                            <button class="btn-action" style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--accent-emerald); color: var(--accent-emerald); padding: 0.25rem 0.6rem; font-size: 0.75rem;" onclick="openReviewModal(${item.product_id}, '${(item.name || 'Product').replace(/'/g, "\\'")}')">
                                                ⭐ Review Product
                                            </button>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; font-size: 0.85rem; margin-top: 0.5rem;">
                            <div>
                                <div><strong>Payment Reference:</strong> <code>${o.payment_reference || 'N/A'}</code></div>
                                ${o.refund_reference ? `<div style="color: var(--accent-rose); margin-top: 0.25rem;"><strong>Refund Reference (Day 27):</strong> <code>${o.refund_reference}</code> (Reason: ${o.refund_reason || 'Dispute'})</div>` : ''}
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                ${['shipped', 'delivered'].includes(o.status) ? `
                                    <button class="btn-action" style="background: rgba(99, 102, 241, 0.15); border: 1px solid var(--primary); color: var(--primary-hover); padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="trackOrderShipment(${o.id})">
                                        🚚 Live Tracking
                                    </button>
                                ` : ''}
                                ${isDisputeEligible && !o.refund_reference ? `
                                    <button class="btn-action" style="background: var(--bg-elevated); border: 1px solid var(--accent-rose); color: var(--accent-rose); padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="openDisputeModal(${o.id})">
                                        ⚠️ File Dispute (Day 27)
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Native Dialog Modal for Disputes (modern-web-guidance compliant)
        function openDisputeModal(orderId) {
            document.getElementById('modal-dispute-order-id').value = orderId;
            document.getElementById('modal-dispute-title').textContent = `File Dispute on Order #${orderId}`;
            document.getElementById('dispute-modal').showModal();
        }

        function closeDisputeModal() {
            document.getElementById('dispute-modal').close();
        }

        async function submitCustomerDispute() {
            const orderId = document.getElementById('modal-dispute-order-id').value;
            const reason = document.getElementById('modal-dispute-reason').value.trim();

            if (!reason) {
                showToast('Please provide a dispute explanation.', 'error');
                return;
            }

            try {
                await callApi(`/api/v1/orders/${orderId}/disputes`, 'POST', { reason });
                showToast('Dispute filed successfully! Administrator notified.', 'success');
                closeDisputeModal();
                fetchCustomerOrders();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Tracking Modal Functions
        async function trackOrderShipment(orderId) {
            try {
                const res = await callApi(`/api/v1/orders/${orderId}/shipments`);
                const shipments = res.data?.shipments || [];
                if (!shipments.length) {
                    showToast('No shipment tracking registered for this order yet.', 'info');
                    return;
                }
                trackTrackingNumber(shipments[0].tracking_number);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function trackTrackingNumber(trackingNumber) {
            try {
                const res = await callApi(`/api/v1/shipments/track/${trackingNumber}`);
                const data = res.data;
                document.getElementById('tracking-modal-number').textContent = data.tracking_number;

                const timelineHtml = (data.timeline || []).map(step => `
                    <div class="timeline-step ${step.completed ? 'completed' : ''} ${step.current ? 'current' : ''}">
                        <div class="timeline-icon-box">${step.icon}</div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: ${step.completed ? 'var(--text-primary)' : 'var(--text-muted)'};">
                                ${step.label} ${step.current ? '<span style="font-size: 0.75rem; color: var(--primary); margin-left: 0.5rem; font-weight: 600;">(Current Milestone)</span>' : ''}
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">${step.description}</div>
                        </div>
                    </div>
                `).join('');

                const container = document.getElementById('tracking-modal-content');
                container.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Tracking Number</div>
                            <code style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">${data.tracking_number}</code>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Carrier Status</div>
                            <span class="status-badge" style="background: rgba(99, 102, 241, 0.2); color: var(--primary-hover); text-transform: capitalize;">
                                ${data.status_icon} ${data.status_label}
                            </span>
                        </div>
                    </div>
                    ${data.carrier ? `<div style="font-size: 0.85rem; margin-bottom: 1rem; color: var(--text-secondary);"><strong>Carrier:</strong> ${data.carrier.toUpperCase()} ${data.estimated_delivery_at ? `• <strong>Est. Delivery:</strong> ${new Date(data.estimated_delivery_at).toLocaleDateString()}` : ''}</div>` : ''}
                    <div class="timeline-container">
                        ${timelineHtml}
                    </div>
                `;

                document.getElementById('tracking-modal').showModal();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function closeTrackingModal() {
            document.getElementById('tracking-modal').close();
        }

        // Review Modal Functions
        function openReviewModal(productId, productName) {
            document.getElementById('modal-review-product-id').value = productId;
            document.getElementById('modal-review-title').textContent = `Write a Review for "${productName}"`;
            document.getElementById('modal-review-rating').value = '5';
            document.getElementById('modal-review-comment').value = '';
            document.getElementById('review-modal').showModal();
        }

        function closeReviewModal() {
            document.getElementById('review-modal').close();
        }

        async function submitCustomerReview() {
            const productId = document.getElementById('modal-review-product-id').value;
            const rating = parseInt(document.getElementById('modal-review-rating').value, 10);
            const comment = document.getElementById('modal-review-comment').value.trim();

            if (!rating || rating < 1 || rating > 5) {
                showToast('Please select a rating between 1 and 5 stars.', 'error');
                return;
            }

            try {
                await callApi(`/api/v1/products/${productId}/reviews`, 'POST', { rating, comment });
                showToast('Review submitted successfully! Thank you for your feedback.', 'success');
                closeReviewModal();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Vendor Portal
        async function fetchVendorDashboard() {
            if (!state.token) return;
            try {
                const res = await callApi('/api/v1/vendor/analytics/dashboard');
                if (res.data) {
                    document.getElementById('vendor-stat-revenue').textContent = `$${parseFloat(res.data.total_revenue || 1938.90).toFixed(2)}`;
                    document.getElementById('vendor-stat-orders').textContent = `${res.data.total_orders || 4} Orders`;
                }
                fetchVendorOrders();
            } catch (err) {
                console.error(err);
            }
        }

        async function fetchVendorOrders() {
            try {
                const res = await callApi('/api/v1/vendor/orders');
                const lines = res.data?.orders?.data || [];
                const c = document.getElementById('vendor-orders-list');

                if (!lines.length) {
                    c.innerHTML = '<div style="color: var(--text-muted); padding: 1.5rem 0;">No pending shipments right now.</div>';
                    return;
                }

                c.innerHTML = lines.map(line => {
                    const shipment = line.shipment;
                    return `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <span style="font-weight: 600;">Order #${line.order_id}</span> • <span>${line.name || 'Product'} (Qty: ${line.quantity})</span>
                            ${shipment ? `
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    Tracking: <code style="cursor: pointer; color: var(--primary);" onclick="trackTrackingNumber('${shipment.tracking_number}')">${shipment.tracking_number}</code>
                                    • Status: <strong style="text-transform: capitalize; color: var(--accent-emerald);">${(shipment.status || '').replace(/_/g, ' ')}</strong>
                                </div>
                            ` : ''}
                        </div>
                        <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                            ${!shipment ? `
                                <button class="btn-action" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="shipVendorLine(${line.order_id}, ${line.id})">
                                    🚚 Ship Item
                                </button>
                            ` : `
                                <button class="btn-action" style="background: rgba(99, 102, 241, 0.15); border: 1px solid var(--primary); color: var(--primary-hover); padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="trackTrackingNumber('${shipment.tracking_number}')">
                                    🔍 Track
                                </button>
                                ${shipment.status === 'processing' ? `
                                    <button class="btn-action" style="background: var(--accent-amber); color: #000; padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="advanceShipmentStatus(${shipment.id}, 'in_transit')">
                                        ✈️ In Transit
                                    </button>
                                ` : ''}
                                ${shipment.status === 'in_transit' ? `
                                    <button class="btn-action" style="background: #3b82f6; color: #fff; padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="advanceShipmentStatus(${shipment.id}, 'out_for_delivery')">
                                        🛵 Out for Delivery
                                    </button>
                                ` : ''}
                                ${shipment.status === 'out_for_delivery' ? `
                                    <button class="btn-action" style="background: var(--accent-emerald); color: #fff; padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="advanceShipmentStatus(${shipment.id}, 'delivered')">
                                        ✅ Delivered
                                    </button>
                                ` : ''}
                                ${shipment.status === 'delivered' ? `
                                    <span style="font-size: 0.75rem; color: var(--accent-emerald); font-weight: 700;">✓ Delivered</span>
                                ` : ''}
                            `}
                        </div>
                    </div>
                `}).join('');
            } catch (err) {
                console.error(err);
            }
        }

        async function advanceShipmentStatus(shipmentId, status) {
            try {
                await callApi(`/api/v1/vendor/shipments/${shipmentId}/status`, 'PATCH', { status });
                showToast(`Shipment updated to ${status.replace(/_/g, ' ')}!`, 'success');
                fetchVendorOrders();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function shipVendorLine(orderId, itemId) {
            try {
                const res = await callApi(`/api/v1/vendor/orders/${orderId}/items/${itemId}/ship`, 'POST');
                showToast(`Shipped! Tracking: ${res.data?.shipment?.tracking_number || 'TRK-LIVE'}`, 'success');
                fetchVendorOrders();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function queueVendorReport() {
            try {
                await callApi('/api/v1/vendor/analytics/reports', 'POST', { type: 'sales' });
                showToast('Sales report queued on background worker!', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Admin Dispute & Refund Center (Day 27)
        async function fetchAdminDisputes() {
            if (!state.token) return;
            try {
                const res = await callApi('/api/v1/admin/disputes');
                const disputes = res.data?.disputes?.data || [];
                document.getElementById('dispute-count').textContent = disputes.filter(d => d.status === 'pending').length;
                renderAdminDisputes(disputes);
            } catch (err) {
                console.error(err);
            }
        }

        function renderAdminDisputes(disputes) {
            const c = document.getElementById('admin-disputes-container');
            if (!disputes.length) {
                c.innerHTML = '<div style="color: var(--text-muted); padding: 1.5rem 0;">No customer disputes currently in the queue.</div>';
                return;
            }

            c.innerHTML = disputes.map(d => `
                <div style="background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <div>
                            <span style="font-weight: 700;">Dispute #${d.id}</span> • <span>Order #${d.order_id}</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.5rem;">(Customer #${d.user_id})</span>
                        </div>
                        <span class="status-badge ${d.status === 'resolved_refunded' ? 'badge-refunded' : (d.status === 'pending' ? 'badge-pending' : 'badge-cancelled')}">${d.status}</span>
                    </div>

                    <div style="font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-secondary);">
                        <strong>Customer Claim:</strong> "${d.reason}"
                    </div>

                    ${d.status === 'pending' ? `
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <button class="btn-action" style="background: var(--accent-rose); font-size: 0.8rem; padding: 0.4rem 0.85rem;" onclick="resolveDisputeRefund(${d.id})">
                                💸 Approve & Issue Gateway Refund
                            </button>
                            <button class="btn-action" style="background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 0.8rem; padding: 0.4rem 0.85rem;" onclick="rejectDispute(${d.id})">
                                ✕ Reject Dispute
                            </button>
                        </div>
                    ` : `
                        <div style="font-size: 0.8rem; color: var(--accent-emerald);">
                            ✓ Concluded: ${d.admin_notes || 'Resolved'}
                        </div>
                    `}
                </div>
            `).join('');
        }

        async function resolveDisputeRefund(disputeId) {
            try {
                const res = await callApi(`/api/v1/admin/disputes/${disputeId}/refund`, 'POST', {
                    admin_notes: 'Approved by admin via live console.'
                });
                showToast('Dispute resolved! Polymorphic gateway refund executed.', 'success');
                fetchAdminDisputes();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function rejectDispute(disputeId) {
            try {
                await callApi(`/api/v1/admin/disputes/${disputeId}/reject`, 'POST', {
                    admin_notes: 'Claim rejected by admin review.'
                });
                showToast('Dispute rejected.', 'success');
                fetchAdminDisputes();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function executeDirectRefund() {
            const orderId = document.getElementById('direct-refund-order-id').value;
            const reason = document.getElementById('direct-refund-reason').value;
            const force = document.getElementById('direct-refund-force').checked;

            try {
                const res = await callApi(`/api/v1/admin/orders/${orderId}/refund`, 'POST', {
                    reason,
                    force
                });

                showToast(`Order #${orderId} Refunded! Ref: ${res.data?.refund?.reference}`, 'success');
                fetchAdminDisputes();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Live API Telemetry Logger
        function logApi(method, url, status, message = '', duration = 0) {
            state.apiCalls++;
            document.getElementById('api-call-count').textContent = state.apiCalls;

            const stream = document.getElementById('api-log-stream');
            const time = new Date().toLocaleTimeString();
            const statusColor = status >= 200 && status < 300 ? 'var(--accent-emerald)' : (status >= 400 ? 'var(--accent-rose)' : 'var(--text-muted)');

            const item = document.createElement('div');
            item.className = 'log-entry';
            item.innerHTML = `
                <span style="color: var(--text-muted); font-size: 0.75rem;">${time}</span>
                <span class="log-method method-${method}">${method}</span>
                <span style="color: var(--text-primary);">${url}</span>
                <span style="color: ${statusColor}; font-weight: bold;">${status}</span>
                <span style="color: var(--text-secondary); flex: 1; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${message}</span>
                ${duration ? `<span style="color: var(--text-muted); font-size: 0.75rem;">${duration}ms</span>` : ''}
            `;

            stream.insertBefore(item, stream.firstChild);
        }

        function toggleApiDrawer() {
            const drawer = document.getElementById('api-drawer');
            const arrow = document.getElementById('drawer-toggle-arrow');
            drawer.classList.toggle('open');
            arrow.textContent = drawer.classList.contains('open') ? '▼' : '▲';
        }

        function openApiDrawer() {
            const drawer = document.getElementById('api-drawer');
            const arrow = document.getElementById('drawer-toggle-arrow');
            if (drawer && !drawer.classList.contains('open')) {
                drawer.classList.add('open');
                if (arrow) arrow.textContent = '▼';
            }
        }

        async function checkSystemHealth() {
            try {
                showToast('Auditing Docker container and service telemetry...', 'info');
                const res = await callApi('/api/v1/system/health');
                const data = res.data;
                const dbStatus = data.services.database.connected ? 'Connected' : 'Disconnected';
                const cacheStatus = data.services.cache.operational ? 'Active' : 'Degraded';
                showToast(`System Health: ${data.status.toUpperCase()} (${data.latency_ms}ms) • DB: ${dbStatus} • Cache: ${cacheStatus} • PHP ${data.runtime.php_version}`, 'success');
                openApiDrawer();
            } catch (err) {
                showToast('System health check error: ' + err.message, 'error');
            }
        }

        // ==========================================
        // Notification Center Logic (Day 30 Feature)
        // ==========================================
        function toggleNotificationsModal() {
            const overlay = document.getElementById('notif-modal-overlay');
            const isOpen = overlay.classList.toggle('open');
            if (isOpen && state.token) {
                fetchUnreadNotificationCount();
                fetchNotifications();
            }
        }

        function handleNotifOverlayClick(e) {
            if (e.target.id === 'notif-modal-overlay') {
                toggleNotificationsModal();
            }
        }

        function setNotifFilter(filter) {
            state.notificationFilter = filter;
            document.getElementById('notif-filter-all').classList.toggle('active', filter === 'all');
            document.getElementById('notif-filter-unread').classList.toggle('active', filter === 'unread');
            fetchNotifications();
        }

        async function refreshNotifications() {
            if (!state.token) {
                showToast('Please select a persona first.', 'error');
                return;
            }
            await fetchUnreadNotificationCount();
            await fetchNotifications();
            showToast('Notifications synchronized.', 'success');
        }

        async function fetchUnreadNotificationCount() {
            if (!state.token) {
                state.unreadNotificationCount = 0;
                updateNotificationBadge();
                return;
            }
            try {
                const res = await callApi('/api/v1/notifications/unread-count');
                state.unreadNotificationCount = res.data?.unread_count || 0;
                updateNotificationBadge();
            } catch (err) {
                console.error('Failed to fetch unread notification count:', err);
            }
        }

        function updateNotificationBadge() {
            const badge = document.getElementById('notification-badge-count');
            const statusText = document.getElementById('notif-unread-status');
            const count = state.unreadNotificationCount;

            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
                statusText.textContent = `${count} unread notification${count === 1 ? '' : 's'}`;
                statusText.style.color = 'var(--accent-amber)';
            } else {
                badge.style.display = 'none';
                statusText.textContent = 'All caught up! No unread notifications.';
                statusText.style.color = 'var(--text-muted)';
            }
        }

        async function fetchNotifications() {
            const stream = document.getElementById('notif-stream');
            if (!state.token) {
                renderNotifications();
                return;
            }
            try {
                let url = '/api/v1/notifications?per_page=25';
                if (state.notificationFilter === 'unread') {
                    url += '&unread=1';
                }
                const res = await callApi(url);
                state.notifications = res.data?.notifications?.data || [];
                renderNotifications();
            } catch (err) {
                stream.innerHTML = `<div style="color: var(--accent-rose); text-align: center; padding: 2rem;">Error: ${err.message}</div>`;
            }
        }

        function renderNotifications() {
            const stream = document.getElementById('notif-stream');
            if (!state.token) {
                stream.innerHTML = `
                    <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                        <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">🔒</span>
                        Please switch to a persona (Customer, Vendor, or Admin) to view your in-app notification center.
                    </div>
                `;
                return;
            }

            if (!state.notifications.length) {
                stream.innerHTML = `
                    <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                        <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📭</span>
                        No ${state.notificationFilter === 'unread' ? 'unread ' : ''}notifications at this time.
                    </div>
                `;
                return;
            }

            stream.innerHTML = state.notifications.map(n => {
                const typeStr = (n.type || '').toLowerCase();
                let icon = '🔔';
                if (typeStr.includes('order') || (n.data && n.data.kind === 'order_alert')) icon = '📦';
                else if (typeStr.includes('payment') || typeStr.includes('refund')) icon = '💳';
                else if (typeStr.includes('ship')) icon = '🚚';
                else if (typeStr.includes('vendor') || typeStr.includes('store')) icon = '🏪';

                const isUnread = !n.is_read;

                return `
                    <div class="notif-card ${isUnread ? 'unread' : ''}" id="notif-card-${n.id}">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.35rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-weight: 600; font-size: 0.9rem;">
                                <span>${icon}</span>
                                <span>${n.title || 'Notification'}</span>
                                ${isUnread ? '<span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--primary);"></span>' : ''}
                            </div>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">${n.created_at_human || 'recently'}</span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.5rem; line-height: 1.4;">
                            ${n.body || (n.data && (n.data.body || n.data.message)) || ''}
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            ${isUnread ? `
                                <button class="btn-action" style="font-size: 0.72rem; padding: 0.25rem 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); color: var(--text-primary);" onclick="markNotificationAsRead('${n.id}')">
                                    Mark Read
                                </button>
                            ` : ''}
                            <button class="btn-action" style="font-size: 0.72rem; padding: 0.25rem 0.55rem; background: transparent; border: 1px solid transparent; color: var(--text-muted);" onclick="deleteNotification('${n.id}')" title="Dismiss">
                                🗑️ Dismiss
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function markNotificationAsRead(id) {
            try {
                await callApi(`/api/v1/notifications/${id}/read`, 'PATCH');
                showToast('Notification marked as read.', 'success');
                await fetchUnreadNotificationCount();
                await fetchNotifications();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function markAllNotificationsAsRead() {
            if (!state.token) return;
            try {
                const res = await callApi('/api/v1/notifications/read-all', 'POST');
                showToast(`Marked ${res.data?.marked_count || 0} notifications as read.`, 'success');
                await fetchUnreadNotificationCount();
                await fetchNotifications();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function deleteNotification(id) {
            try {
                await callApi(`/api/v1/notifications/${id}`, 'DELETE');
                showToast('Notification dismissed.', 'success');
                await fetchUnreadNotificationCount();
                await fetchNotifications();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Toasts
        function showToast(text, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<span>${type === 'success' ? '✓' : '⚠'}</span><span>${text}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Gotham City Coffee' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --bg-dark: #0b4d3f;
                --bg-panel: #e6e8e4;
                --panel: #f1f3f0;
                --card: #f5f5f3;
                --line: #d4d5d1;
                --text: #1c1c1c;
                --muted: #6b6d6a;
                --green: #0d5b4d;
                --green-soft: #dfeee9;
                --green-strong: #0b7b3d;
                --green-dark: #1f5d47;
                --red: #d5453d;
                --orange: #f2a43a;
                --shadow: rgba(0, 0, 0, 0.08);
            }

            * { box-sizing: border-box; }

            html, body {
                margin: 0;
                min-height: 100vh;
                min-height: 100dvh;
                background: var(--bg-dark);
                color: var(--text);
                font-family: Arial, Helvetica, sans-serif;
            }

            body {
                display: flex;
                justify-content: center;
                align-items: flex-start;
                padding: 0;
            }

            .phone-shell {
                width: 100%;
                max-width: 420px;
                min-height: 100vh;
                min-height: 100dvh;
                background: var(--bg-panel);
                overflow: hidden;
                position: relative;
            }

            .statusbar {
                height: calc(48px + env(safe-area-inset-top, 0px));
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: env(safe-area-inset-top, 0px) 18px 0;
                background: rgba(255,255,255,0.02);
                color: #232323;
                font-size: 15px;
                font-weight: 700;
            }

            .statusbar .left,
            .statusbar .right {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .signal {
                display: flex;
                gap: 3px;
                align-items: flex-end;
            }

            .signal span {
                width: 4px;
                border-radius: 2px;
                background: #1e1e1e;
                display: block;
            }

            .signal span:nth-child(1){ height: 5px; }
            .signal span:nth-child(2){ height: 8px; }
            .signal span:nth-child(3){ height: 11px; }
            .signal span:nth-child(4){ height: 14px; }

            .battery {
                width: 22px;
                height: 11px;
                border: 2px solid #1e1e1e;
                border-radius: 3px;
                position: relative;
                display: inline-block;
            }

            .battery:after {
                content: "";
                position: absolute;
                right: -4px;
                top: 2px;
                width: 2px;
                height: 5px;
                background: #1e1e1e;
                border-radius: 1px;
            }

            .battery:before {
                content: "";
                position: absolute;
                inset: 1px 2px 1px 2px;
                border-radius: 2px;
                background: #1e1e1e;
            }

            .screen {
                background: var(--bg-panel);
                min-height: calc(100vh - 48px);
                min-height: calc(100dvh - 48px - env(safe-area-inset-top, 0px));
            }

            .page {
                padding: 18px 18px calc(30px + env(safe-area-inset-bottom, 0px));
            }

            .login-card {
                margin: 70px auto 0;
                width: 100%;
                max-width: 330px;
                background: #f1f1f1;
                border-radius: 18px;
                padding: 28px 22px 18px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }

            .brand-mark {
                width: 176px;
                height: 176px;
                margin: 0 auto 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .brand-mark img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
                mix-blend-mode: multiply;
            }

            .brand-mark::before {
                content: none;
            }

            .brand-mark::after {
                content: none;
            }

            .brand-text {
                font-size: 28px;
                font-weight: 700;
                text-align: center;
                margin: 0;
                letter-spacing: -0.03em;
            }

            .brand-sub {
                text-align: center;
                color: var(--muted);
                font-size: 16px;
                margin: 8px 0 18px;
            }

            .field {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 14px;
            }

            .field label {
                font-size: 13px;
                font-weight: 600;
                color: #2b2b2b;
            }

            .input-wrap {
                position: relative;
                display: flex;
                align-items: center;
                background: #f6f7f6;
                border: 1px solid #d3d4d1;
                border-radius: 10px;
                padding: 0 10px;
                height: 52px;
            }

            .input-wrap .icon {
                width: 22px;
                text-align: center;
                color: #2f2f2f;
                font-size: 18px;
                opacity: 0.8;
            }

            .input-wrap input {
                border: none;
                background: transparent;
                outline: none;
                flex: 1;
                font-size: 15px;
                padding: 10px 8px;
                color: #1c1c1c;
            }

            .btn-primary {
                width: 100%;
                margin-top: 8px;
                height: 48px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(180deg, #0f5a4d 0%, #0b4e3f 100%);
                color: white;
                font-size: 18px;
                font-weight: 700;
                cursor: pointer;
                letter-spacing: -0.01em;
            }

            .login-help {
                margin-top: 20px;
                text-align: center;
                font-size: 13px;
                color: #3a3a3a;
                line-height: 1.5;
            }

            .login-help strong {
                font-weight: 700;
            }

            .dashboard-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 0 16px;
            }

            .dashboard-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                letter-spacing: -0.04em;
            }

            .live-sync-status {
                min-height: 16px;
                margin: -8px 0 8px;
                color: var(--muted);
                font-size: 11px;
                line-height: 1.4;
            }

            .icon-btn {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                background: translucent;
                border: none;
                font-size: 26px;
                color: #1d1d1d;
                display: grid;
                place-items: center;
            }

            .metrics-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-top: 12px;
            }

            .metric-card {
                background: rgba(255,255,255,0.15);
                border-radius: 12px;
                padding: 16px 16px 14px;
                border: 1px solid var(--line);
                min-height: 100px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .metric-card .row {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 14px;
                color: var(--muted);
            }

            .metric-card .value {
                font-size: 16px;
                font-weight: 700;
                color: #1c1c1c;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .metric-card .bullet {
                width: 30px;
                height: 30px;
                display: grid;
                place-items: center;
                border-radius: 10px;
                background: rgba(18,79,66,0.06);
                font-size: 18px;
            }

            .metric-card.total .bullet { color: #1a5f4d; }
            .metric-card.income .bullet { color: #1f7c5b; }
            .metric-card.active .bullet { color: #be6a2b; }
            .metric-card.pending .bullet { color: #d34949; }

            .section-title {
                margin: 20px 0 12px;
                font-size: 20px;
                font-weight: 700;
                letter-spacing: -0.03em;
            }

            .search-box {
                width: 100%;
                height: 48px;
                border-radius: 10px;
                border: 1px solid var(--line);
                background: rgba(255,255,255,0.18);
                padding: 0 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #4d4d4d;
                font-size: 18px;
            }

            .search-box input {
                border: none;
                background: transparent;
                outline: none;
                flex: 1;
                font-size: 16px;
                color: #323232;
            }

            .menu-list {
                margin-top: 14px;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .search-empty-state {
                padding: 14px;
                color: var(--muted);
                text-align: center;
                font-size: 14px;
            }

            .menu-item {
                background: rgba(255,255,255,0.14);
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 12px 14px;
                display: grid;
                grid-template-columns: 52px 1fr auto;
                align-items: center;
                gap: 12px;
                min-height: 86px;
            }

            .waiter-page {
                padding-top: 0;
            }

            .barista-page {
                padding-top: 0;
            }

            .barista-topbar {
                position: sticky;
                top: 0;
                z-index: 10;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 4px;
                margin: 0 -18px 18px;
                padding: 8px 12px;
                background: var(--bg-panel);
                border-bottom: 1px solid var(--line);
            }

            .barista-topbar h1 {
                flex: 1;
                margin: 0;
                font-size: 21px;
                font-weight: 700;
            }

            .barista-topbar .icon-btn {
                flex-shrink: 0;
                width: 38px;
                height: 42px;
                font-size: 24px;
                text-decoration: none;
                color: var(--text);
            }

            .barista-section-heading {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin: 12px 0 18px;
            }

            .barista-section-heading h2,
            .barista-section > h2,
            .barista-section-title {
                margin: 0 0 14px;
                color: #58675f;
                font-size: 20px;
                font-weight: 700;
            }

            .period-select {
                position: relative;
                display: grid;
                gap: 1px;
                min-width: 120px;
                padding: 2px 10px 5px;
                border: 1px solid var(--green);
                border-radius: 9px;
                background: #fff;
            }

            .period-select span {
                color: var(--green);
                font-size: 12px;
            }

            .period-select select {
                width: 100%;
                border: 0;
                outline: 0;
                background: transparent;
                color: var(--text);
                font: inherit;
                font-weight: 700;
            }

            .barista-summary-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin: 0 4px 52px;
            }

            .barista-summary-card {
                display: grid;
                gap: 7px;
                min-height: 78px;
                padding: 14px;
                border: 1px solid var(--line);
                border-radius: 9px;
                background: rgba(255,255,255,0.82);
                color: #58675f;
            }

            .barista-summary-card strong {
                font-size: 18px;
            }

            .barista-income { color: #35a853; }
            .barista-expense { color: #f04438; }
            .barista-profit { color: #1683e8; }

            .barista-section {
                margin-bottom: 36px;
            }

            .barista-section h3 {
                margin: 0 0 12px;
                color: var(--text);
                font-size: 16px;
            }

            .barista-transaction-row,
            .barista-incoming-order,
            .barista-catalog-card,
            .barista-recent-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px;
                border: 1px solid var(--line);
                border-radius: 9px;
                background: rgba(255,255,255,0.84);
            }

            .barista-transaction-row > div,
            .barista-incoming-order > div,
            .barista-catalog-info {
                display: grid;
                flex: 1;
                gap: 4px;
                min-width: 0;
            }

            .barista-transaction-row span,
            .barista-incoming-order span,
            .barista-transaction-row small,
            .barista-incoming-order small,
            .barista-catalog-info span,
            .barista-catalog-info small {
                color: #58675f;
                overflow-wrap: anywhere;
            }

            .barista-transaction-row > strong {
                flex-shrink: 0;
            }

            .barista-incoming-order {
                align-items: flex-start;
            }

            .incoming-order-icon {
                width: 30px;
                flex-shrink: 0;
                padding-top: 2px;
                font-size: 22px;
            }

            .barista-recent-row {
                justify-content: space-between;
                border-width: 0 0 1px;
                border-radius: 0;
                background: transparent;
            }

            .barista-menu-link {
                display: block;
                margin-top: 26px;
                padding: 0 0 8px;
                border-bottom: 1px solid var(--line);
                color: var(--text);
                font-size: 20px;
                font-weight: 700;
                text-decoration: none;
            }

            .barista-catalog-list {
                margin-top: 12px;
            }

            .barista-catalog-card {
                min-height: 130px;
            }

            .barista-catalog-card .waiter-photo {
                flex-shrink: 0;
            }

            .barista-menu-price {
                flex-shrink: 0;
                color: var(--green);
            }

            .barista-menu-status.active {
                color: var(--green-strong);
                font-weight: 700;
            }

            .barista-menu-status.inactive {
                color: var(--red);
                font-weight: 700;
            }

            .barista-menu-toggle {
                min-height: 34px;
                padding: 6px 12px;
                border: 0;
                border-radius: 8px;
                color: white;
                font: inherit;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
            }

            .barista-menu-toggle.activate {
                background: var(--green-strong);
            }

            .barista-menu-toggle.deactivate {
                background: var(--red);
            }

            .order-completion-toast {
                position: fixed;
                z-index: 1000;
                right: 16px;
                bottom: 20px;
                left: 16px;
                max-width: 388px;
                margin: 0 auto;
                padding: 14px 16px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                border-radius: 12px;
                background: var(--green);
                color: #fff;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);
                font-size: 14px;
                line-height: 1.45;
            }

            .order-completion-toast[hidden] {
                display: none;
            }

            .barista-empty-state {
                padding: 8px 0;
                color: #58675f;
                text-align: center;
            }

            .barista-form-field {
                display: grid;
                gap: 5px;
                padding: 7px 12px;
                border: 1px solid var(--line);
                border-radius: 9px;
                color: var(--green);
                font-size: 13px;
            }

            .barista-form-field select {
                border: 0;
                background: transparent;
                color: var(--text);
                font: inherit;
                font-size: 16px;
            }

            .barista-transaction-sheet .sheet-title {
                font-size: 24px;
            }

            .waiter-topbar {
                position: sticky;
                top: 0;
                z-index: 10;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                margin: 0 -18px 18px;
                padding: 8px 18px;
                background: var(--bg-panel);
                border-bottom: 1px solid var(--line);
            }

            .waiter-topbar h1 {
                margin: 0;
                font-size: 22px;
                font-weight: 700;
            }

            .waiter-customer-field {
                background: #fff;
                height: 56px;
            }

            .waiter-menu-list {
                margin-top: 16px;
            }

            .waiter-menu-card {
                display: grid;
                grid-template-columns: 84px minmax(0, 1fr) 88px;
                align-items: center;
                gap: 12px;
                min-height: 112px;
                padding: 12px;
                background: rgba(255,255,255,0.88);
                border: 1px solid var(--line);
                border-radius: 10px;
            }

            .waiter-photo {
                width: 84px;
                height: 84px;
                display: grid;
                place-items: center;
                overflow: hidden;
                border-radius: 8px;
                background: var(--green-soft);
                font-size: 28px;
            }

            .waiter-photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .waiter-menu-info {
                display: flex;
                flex-direction: column;
                gap: 5px;
                min-width: 0;
                color: #58675f;
                font-size: 15px;
            }

            .waiter-menu-info strong {
                overflow-wrap: anywhere;
                color: #58675f;
                font-size: 16px;
            }

            .waiter-menu-info b,
            .waiter-order-total {
                color: var(--green);
                font-size: 16px;
            }

            .quantity-controls {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 4px;
            }

            .quantity-controls span {
                min-width: 18px;
                text-align: center;
                color: #58675f;
            }

            .quantity-button {
                width: 30px;
                height: 30px;
                display: grid;
                place-items: center;
                padding: 0;
                border: 1px solid #87928c;
                border-radius: 50%;
                background: transparent;
                color: #1c2923;
                font-size: 22px;
                line-height: 1;
                cursor: pointer;
            }

            .waiter-submit {
                margin: 16px 0 22px;
            }

            .waiter-submit:disabled {
                opacity: 0.55;
                cursor: not-allowed;
            }

            .waiter-active-orders {
                padding-top: 18px;
                border-top: 1px solid var(--line);
            }

            .waiter-order-card {
                position: relative;
                padding: 14px;
                background: rgba(255,255,255,0.88);
                border: 1px solid var(--line);
                border-radius: 10px;
            }

            .waiter-order-heading {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                font-size: 16px;
            }

            .waiter-order-meta {
                display: grid;
                gap: 4px;
                margin-top: 6px;
                color: #535f58;
                font-size: 14px;
                overflow-wrap: anywhere;
            }

            .waiter-order-total {
                display: block;
                margin-top: 8px;
            }

            .payment-option {
                width: 100%;
                min-height: 52px;
                padding: 10px 12px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fff;
                color: var(--text);
                text-align: left;
                font-size: 16px;
                cursor: pointer;
            }

            .menu-no {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: #0d5b4d;
                color: white;
                font-weight: 700;
                display: grid;
                place-items: center;
                font-size: 15px;
            }

            .thumb {
                width: 52px;
                height: 52px;
                border-radius: 12px;
                background: linear-gradient(135deg, #c4a37d, #8b5c3b 60%, #2b1b13);
                display: grid;
                place-items: center;
                color: rgba(255,255,255,0.96);
                font-size: 26px;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
                overflow: hidden;
            }

            .thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .menu-name {
                font-size: 18px;
                font-weight: 600;
                margin: 0;
            }

            .menu-meta {
                margin-top: 3px;
                color: #595d59;
                font-size: 14px;
            }

            .unit-badge {
                font-size: 15px;
                color: #1c1c1c;
                font-weight: 600;
                min-width: 48px;
                text-align: right;
            }

            .quick-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0,1fr));
                gap: 14px;
                margin-top: 16px;
            }

            .quick-card {
                border: 1px solid var(--line);
                background: rgba(255,255,255,0.14);
                border-radius: 12px;
                padding: 18px 14px 16px;
                min-height: 104px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 12px;
                font-weight: 600;
                font-size: 15px;
            }

            .quick-card .icon-large {
                width: 40px;
                height: 40px;
                display: grid;
                place-items: center;
                font-size: 26px;
                color: var(--green-dark);
            }

            .tabbar {
                display: flex;
                gap: 8px;
                margin: 14px 0 16px;
                border-bottom: 1px solid var(--line);
                padding-bottom: 8px;
            }

            .tab {
                flex: 1;
                text-align: center;
                font-size: 14px;
                font-weight: 600;
                color: #4d4d4d;
                padding: 8px 4px;
                position: relative;
            }

            .tab.active {
                color: #1c1c1c;
            }

            .tab.active:after {
                content: "";
                position: absolute;
                left: 12%;
                right: 12%;
                bottom: -9px;
                height: 4px;
                border-radius: 3px;
                background: #e0b277;
            }

            .toggle {
                width: 42px;
                height: 24px;
                border-radius: 999px;
                background: var(--red);
                position: relative;
                display: inline-block;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.03);
            }

            .toggle:before {
                content: "";
                position: absolute;
                width: 18px;
                height: 18px;
                left: 3px;
                top: 3px;
                background: #f4f7f4;
                border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,0.18);
            }

            .toggle.on {
                background: var(--green-strong);
            }

            .toggle.on:before {
                left: 20px;
            }

            .category-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: rgba(255,255,255,0.16);
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 16px 16px;
                min-height: 76px;
                margin-bottom: 10px;
            }

            .category-name {
                font-size: 18px;
                font-weight: 600;
            }

            .category-desc {
                display: block;
                color: #5d5d5d;
                font-size: 13px;
                margin-top: 4px;
            }

            .more-btn {
                font-size: 28px;
                color: #2a2a2a;
                line-height: 1;
                display: inline-block;
                transform: translateY(-5px);
                background: transparent;
                border: none;
                cursor: pointer;
            }

            .menu-more-wrap {
                position: relative;
            }

            .menu-action-menu {
                position: absolute;
                right: 0;
                top: calc(100% + 8px);
                background: rgba(255,255,255,0.96);
                border: 1px solid #d6d8d4;
                border-radius: 12px;
                box-shadow: 0 12px 24px rgba(0,0,0,0.12);
                min-width: 160px;
                z-index: 20;
            }

            .menu-action-item {
                display: block;
                width: 100%;
                border: none;
                background: transparent;
                text-align: left;
                padding: 12px 14px;
                font-size: 15px;
                color: #1b1b1b;
                cursor: pointer;
            }

            .menu-action-item.danger {
                color: #b42318;
            }

            .fab {
                position: fixed;
                right: max(18px, calc((100vw - 420px) / 2 + 18px));
                bottom: calc(18px + env(safe-area-inset-bottom, 0px));
                width: 62px;
                height: 62px;
                border: none;
                border-radius: 50%;
                background: linear-gradient(180deg, #0d5b4d 0%, #0a4b3b 100%);
                color: white;
                font-size: 42px;
                line-height: 1;
                box-shadow: 0 12px 28px rgba(9, 71, 60, 0.28);
                cursor: pointer;
            }

            .modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(10, 11, 10, 0.25);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 20px;
                z-index: 30;
            }

            .modal-overlay.show {
                display: flex;
            }

            .sheet {
                width: min(86vw, 420px);
                max-height: calc(100vh - 40px);
                max-height: calc(100dvh - 40px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
                overflow-y: auto;
                overscroll-behavior: contain;
                background: rgba(255, 255, 255, 0.96);
                border-radius: 20px;
                padding: 22px 18px 18px;
                box-shadow: 0 22px 46px rgba(0, 0, 0, 0.12);
            }

            .sheet.small {
                width: min(78vw, 360px);
            }

            .sheet-title {
                font-size: clamp(30px, 4vw, 42px);
                font-weight: 800;
                letter-spacing: -0.05em;
                margin: 0 0 18px;
                text-align: center;
                color: #1d1d1d;
            }

            .sheet-form {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .sheet-form input,
            .sheet-form select {
                width: 100%;
                height: 54px;
                border-radius: 12px;
                border: 1px solid #d6d8d4;
                background: rgba(255,255,255,0.18);
                padding: 0 16px;
                font-size: 17px;
                color: #262626;
                outline: none;
            }

            .sheet-form input::placeholder,
            .sheet-form select {
                color: #2a2a2a;
            }

            .upload-box {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                height: 54px;
                border-radius: 12px;
                border: 1px solid #d6d8d4;
                background: rgba(255,255,255,0.18);
                padding: 0 16px;
                font-size: 17px;
                color: #1f1f1f;
                cursor: pointer;
            }

            .upload-box input {
                position: absolute;
                inset: 0;
                opacity: 0;
                cursor: pointer;
            }

            .upload-box .upload-label-text {
                pointer-events: none;
            }

            .upload-icon {
                font-size: 22px;
                color: #1f1f1f;
            }

            .upload-preview.hidden,
            .current-image-preview.hidden {
                display: none;
            }

            .sheet-actions {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 24px;
                margin-top: 8px;
            }

            .action-link {
                background: transparent;
                border: none;
                color: #1f1f1f;
                font-size: 18px;
                font-weight: 700;
                cursor: pointer;
            }

            .action-primary {
                background: transparent;
                border: none;
                color: #0d5b4d;
                font-size: 18px;
                font-weight: 800;
                cursor: pointer;
            }

            .list-header {
                font-size: 22px;
                font-weight: 700;
                margin: 10px 0 12px;
                letter-spacing: -0.03em;
            }

            .status-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }

            .status-meta {
                display: flex;
                flex-direction: column;
                gap: 2px;
                font-size: 14px;
            }

            .status-name {
                font-size: 18px;
                font-weight: 600;
            }

            .status-email {
                color: #525a55;
            }

            .summary-box {
                background: rgba(255,255,255,0.16);
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 14px 16px;
                margin-bottom: 10px;
            }

            .summary-box h3 {
                margin: 0 0 10px;
                font-size: 18px;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .summary-card {
                background: rgba(255,255,255,0.14);
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 16px 12px;
                text-align: center;
            }

            .summary-card .icon {
                font-size: 26px;
                color: var(--green-dark);
            }

            .summary-card .label {
                margin-top: 8px;
                color: #4a4f4d;
                font-size: 14px;
            }

            .summary-card .amount {
                margin-top: 4px;
                font-size: 18px;
                font-weight: 700;
            }

            .report-list {
                border: 1px solid var(--line);
                border-radius: 12px;
                background: rgba(255,255,255,0.16);
                overflow: hidden;
            }

            .report-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 14px 16px;
                border-bottom: 1px solid var(--line);
            }

            .report-row:last-child { border-bottom: none; }

            .report-row small {
                color: #5d6561;
                display: block;
                margin-top: 4px;
            }

            .label-positive { color: var(--green-strong); font-weight: 700; }
            .label-negative { color: var(--red); font-weight: 700; }
            .label-info { color: #0d5b4d; font-weight: 700; }

            .bottom-fab {
                position: fixed;
                right: max(18px, calc((100vw - 420px) / 2 + 18px));
                bottom: calc(18px + env(safe-area-inset-bottom, 0px));
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(180deg, #0d5b4d 0%, #0b4d3f 100%);
                border: none;
                color: white;
                font-size: 42px;
                line-height: 1;
                box-shadow: 0 10px 20px rgba(13, 91, 77, 0.28);
            }

            @media (max-width: 480px) {
                .phone-shell { max-width: 100%; }
                .page { padding-left: 14px; padding-right: 14px; }
                .waiter-topbar { margin-left: -14px; margin-right: -14px; padding-left: 14px; padding-right: 14px; }
                .barista-topbar { margin-left: -14px; margin-right: -14px; padding-left: 10px; padding-right: 10px; }
            }

            @media (max-width: 350px) {
                .barista-topbar h1 { font-size: 18px; }
                .barista-topbar .icon-btn { width: 32px; }
                .barista-summary-grid { gap: 8px; margin-left: 0; margin-right: 0; }
                .barista-summary-card { padding: 10px; }
                .barista-catalog-card { gap: 8px; padding: 10px; }
                .barista-catalog-card .waiter-photo { width: 64px; height: 64px; }
                .barista-catalog-info strong { font-size: 14px; }
                .barista-menu-price { font-size: 14px; }
                .waiter-menu-card {
                    grid-template-columns: 64px minmax(0, 1fr) 76px;
                    gap: 8px;
                    padding: 10px;
                }

                .waiter-photo { width: 64px; height: 64px; }
                .waiter-menu-info strong { font-size: 14px; }
                .waiter-menu-info { font-size: 13px; }
                .quantity-button { width: 24px; height: 24px; font-size: 18px; }
            }
        </style>
    </head>
    <body>
        @if (session('owner_logged_in'))
            <div
                id="order-completion-notifications"
                data-order-completion-notifications
                data-account-key="owner"
                data-initial-paid-at="{{ now()->toIso8601String() }}"
                data-initial-order-id="0"
            ></div>
        @elseif (session('waiter_logged_in') && session('waiter_id'))
            <div
                id="order-completion-notifications"
                data-order-completion-notifications
                data-account-key="waiter-{{ session('waiter_id') }}"
                data-initial-paid-at="{{ now()->toIso8601String() }}"
                data-initial-order-id="0"
            ></div>
        @elseif (session('barista_logged_in') && session('barista_id'))
            <div
                id="order-completion-notifications"
                data-order-completion-notifications
                data-account-key="barista-{{ session('barista_id') }}"
                data-initial-paid-at="{{ now()->toIso8601String() }}"
                data-initial-order-id="0"
            ></div>
        @endif
        <div id="order-completion-toast" class="order-completion-toast" role="status" aria-live="polite" hidden></div>
        <div class="phone-shell">
            <div class="statusbar">
                <div class="left">
                    <span>18:19</span>
                </div>
                <div class="right">
                    <div class="signal"><span></span><span></span><span></span><span></span></div>
                    <div class="battery"></div>
                </div>
            </div>
            <div class="screen">
                @yield('content')
            </div>
        </div>
        <script>
            document.querySelectorAll('[data-search-target]').forEach(function (input) {
                const list = document.getElementById(input.dataset.searchTarget);

                if (! list) {
                    return;
                }

                const emptyState = document.createElement('div');
                emptyState.className = 'search-empty-state';
                emptyState.textContent = 'Tidak ada hasil.';
                emptyState.hidden = true;
                list.after(emptyState);

                input.addEventListener('input', function () {
                    const query = input.value.trim().toLocaleLowerCase();
                    let visibleItems = 0;
                    const items = Array.from(list.querySelectorAll('[data-search-item]'));

                    items.forEach(function (item) {
                        const matches = item.textContent.toLocaleLowerCase().includes(query);
                        item.hidden = ! matches;
                        visibleItems += matches ? 1 : 0;
                    });

                    emptyState.hidden = query.length === 0 || visibleItems > 0;
                });
            });
        </script>
    </body>
</html>

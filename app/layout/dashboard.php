<?php

declare(strict_types=1);

function app_render_dashboard_start(array $user): void
{
    ?>
    <div class="dashboard-wrapper">
        <?php app_render_dashboard_sidebar(); ?>
        
        <div class="dashboard-content">
            <?php app_render_dashboard_header($user); ?>
            
            <main class="dashboard-main">
                <div class="container-fluid py-4">
    <?php
}

function app_render_dashboard_end(): void
{
    ?>
                </div>
            </main>
        </div>
    </div>
    <?php
}

function app_render_dashboard_css(): void
{
    ?>
    <style>
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .dashboard-sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }
        
        .dashboard-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Fix for chat-box height issue - remove bottom padding from dashboard .py-4 */
        .dashboard-content .py-4 {
            padding-bottom: 0 !important;
        }
        
        .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 99;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .dashboard-main {
            flex: 1;
            background-color: #f8f9fa;
        }
        
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .nav-link {
            color: rgba(255,255,255,.8);
            padding: .75rem 1rem;
            border-left: 3px solid transparent;
            transition: all .2s;
        }
        
        .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-left-color: #0d6efd;
        }
        
        .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-left-color: #0d6efd;
        }
        
.nav-link i, .nav-link svg {
            width: 20px;
            height: 20px;
            text-align: center;
            display: inline-block;
            vertical-align: middle;
        }

        .avatar-placeholder {
            font-size: 14px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .dashboard-sidebar {
                width: 70px;
            }

            .dashboard-content {
                margin-left: 70px;
            }

            .dashboard-content.collapsed {
                margin-left: 0;
            }

            .nav-link span {
                display: none;
            }

            .nav-link i {
                margin-right: 0;
            }
        }
    </style>
    <?php
}
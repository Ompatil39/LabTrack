<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/security.php';

use LabTrack\Controller\LabController;
use LabTrack\Controller\WorkstationController;
use LabTrack\Controller\AgentController;
use LabTrack\Controller\AssetController;
use LabTrack\Controller\AuditLogController;
use LabTrack\Controller\AuthController;
use LabTrack\Controller\DashboardController;
use LabTrack\Controller\GrievanceController;
use LabTrack\Controller\MaintenanceController;
use LabTrack\Controller\NotificationController;
use LabTrack\Controller\ProfileController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Router\Router;

startSecureSession();

$request = Request::createFromGlobals();
$router = new Router();

// Labs Module routes
$router->get('/labs', [LabController::class, 'index']);
$router->get('/labs/create', [LabController::class, 'create']);
$router->post('/labs', [LabController::class, 'store']);
$router->get('/labs/{id}', [LabController::class, 'show']);
$router->get('/labs/{id}/edit', [LabController::class, 'edit']);
$router->post('/labs/{id}', [LabController::class, 'update']);
$router->get('/labs/{lab}/workstations', [WorkstationController::class, 'byLab']);

// Workstations Module routes
$router->get('/workstations', [WorkstationController::class, 'index']);
$router->get('/workstations/create', [WorkstationController::class, 'create']);
$router->post('/workstations', [WorkstationController::class, 'store']);
$router->get('/workstations/{id}', [WorkstationController::class, 'show']);
$router->get('/workstations/{id}/edit', [WorkstationController::class, 'edit']);
$router->post('/workstations/{id}', [WorkstationController::class, 'update']);
$router->post('/workstations/{id}/swap', [WorkstationController::class, 'swap']);

// Phase 6 Agent & Telemetry routes
$router->post('/workstations/{id}/enroll-token', [AgentController::class, 'createEnrollToken']);
$router->post('/workstations/{id}/drift/{driftId}/resolve', [AgentController::class, 'resolveDrift']);
$router->get('/api/workstations/{id}/telemetry', [AgentController::class, 'telemetry']);
$router->post('/api/agent/enroll', [AgentController::class, 'enroll']);
$router->post('/api/agent/heartbeat', [AgentController::class, 'heartbeat']);
$router->post('/api/agent/inventory', [AgentController::class, 'inventory']);

// Hardware & Assets Module routes
$router->get('/inventory', [AssetController::class, 'index']);
$router->get('/assets', [AssetController::class, 'index']);
$router->get('/assets/create', [AssetController::class, 'create']);
$router->post('/assets', [AssetController::class, 'store']);
$router->get('/assets/{tag}', [AssetController::class, 'show']);
$router->get('/assets/{tag}/edit', [AssetController::class, 'edit']);
$router->post('/assets/{tag}', [AssetController::class, 'update']);
$router->post('/assets/{tag}/delete', [AssetController::class, 'destroy']);
$router->post('/assets/{tag}/retire', [AssetController::class, 'retire']);
$router->get('/assets/{tag}/qr', [AssetController::class, 'qr']);
$router->get('/a/{tag}', [AssetController::class, 'mobileLanding']);
$router->get('/assets/export-pdf', [AssetController::class, 'exportPdf']);
$router->post('/assets/export-pdf', [AssetController::class, 'exportPdf']);

// API JSON endpoints
$router->get('/api/assets', [AssetController::class, 'apiSearch']);
$router->get('/api/assets/select', [AssetController::class, 'apiSelect']);

// Grievances Module routes
$router->get('/grievances/submit', [GrievanceController::class, 'submit']);
$router->post('/grievances', [GrievanceController::class, 'store']);
$router->get('/grievances', [GrievanceController::class, 'index']);
$router->get('/tickets/track', [GrievanceController::class, 'track']);
$router->get('/tickets/track/{code}', [GrievanceController::class, 'track']);
$router->get('/grievances/{id}', [GrievanceController::class, 'show']);
$router->post('/grievances/{id}/status', [GrievanceController::class, 'updateStatus']);
$router->post('/grievances/{id}/comments', [GrievanceController::class, 'addComment']);
$router->post('/grievances/{id}/delete', [GrievanceController::class, 'destroy']);

// Maintenance Module routes
$router->get('/maintenance', [MaintenanceController::class, 'index']);
$router->post('/maintenance', [MaintenanceController::class, 'store']);
$router->get('/maintenance/reports', [MaintenanceController::class, 'reports']);

// Dashboard Module routes
$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/admin', [DashboardController::class, 'admin']);

// Notifications Module routes
$router->get('/notifications', [NotificationController::class, 'index']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

// Profile & User Management routes
$router->get('/profile', [ProfileController::class, 'show']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->get('/admin/users', [ProfileController::class, 'manageUsers']);
$router->post('/admin/users', [ProfileController::class, 'manageUsers']);
$router->get('/admin/audit-logs', [AuditLogController::class, 'index']);

// Authentication routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/captcha', [AuthController::class, 'captcha']);

// Dispatch and send response
$response = $router->dispatch($request);
$response->send();

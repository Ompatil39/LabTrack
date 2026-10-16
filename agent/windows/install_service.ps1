#requires -Version 5.1
<#
.SYNOPSIS
    Installs the LabTrack Monitoring Agent as a Windows Scheduled Task running as SYSTEM.
.DESCRIPTION
    Must be executed with elevated Administrator privileges.
    Sets up an automatic startup task at boot with failure restart policies, and tightens ACL on agent_config.json.
#>

[CmdletBinding()]
param(
    [string]$AgentScriptPath = "$PSScriptRoot\..\labtrack_agent.py",
    [string]$PythonExePath = "python.exe"
)

# 1. Enforce Administrator rights
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Error "ERROR: This script must be run as Administrator. Please reopen PowerShell as an elevated Administrator."
    exit 1
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AgentScriptFull = (Resolve-Path $AgentScriptPath -ErrorAction Stop).Path
$AgentDir = Split-Path -Parent $AgentScriptFull
$ConfigFile = Join-Path $AgentDir "agent_config.json"

Write-Host "=== Installing LabTrack Agent Task ===" -ForegroundColor Cyan
Write-Host "Agent Script: $AgentScriptFull"
Write-Host "Working Dir:  $AgentDir"

# 2. Check if agent is enrolled
if (-not (Test-Path $ConfigFile)) {
    Write-Warning "WARNING: $ConfigFile not found. Please enroll the agent first with:"
    Write-Warning "  python $AgentScriptFull --enroll --server <SERVER_URL> --token <TOKEN>"
} else {
    # 3. Apply restricted ACL to agent_config.json (SYSTEM and Administrators only)
    Write-Host "Securing $ConfigFile with restricted ACL..." -ForegroundColor Yellow
    icacls.exe $ConfigFile /inheritance:r /grant:r "*S-1-5-18:(F)" "*S-1-5-32-544:(F)" | Out-Null
    Write-Host "ACL configured: Only SYSTEM and Administrators have access." -ForegroundColor Green
}

# 4. Create Scheduled Task action and trigger
$TaskName = "LabTrackMonitoringAgent"
$Action = New-ScheduledTaskAction -Execute $PythonExePath -Argument "`"$AgentScriptFull`"" -WorkingDirectory $AgentDir
$Trigger = New-ScheduledTaskTrigger -AtStartup

# 5. Settings: Restart on failure, run indefinitely
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries `
                                        -DontStopIfGoingOnBatteries `
                                        -RestartCount 3 `
                                        -RestartInterval (New-TimeSpan -Minutes 1) `
                                        -ExecutionTimeLimit (New-TimeSpan -Days 0) `
                                        -MultipleInstances IgnoreNew

# 6. Register task running as SYSTEM (Highest Privileges)
$Principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest

# Remove previous registration if exists
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue

Register-ScheduledTask -TaskName $TaskName `
                       -Action $Action `
                       -Trigger $Trigger `
                       -Settings $Settings `
                       -Principal $Principal `
                       -Description "LabTrack Workstation Monitoring Agent background telemetry daemon" | Out-Null

Write-Host "Successfully registered scheduled task '$TaskName'." -ForegroundColor Green
Write-Host "Starting task now..."
Start-ScheduledTask -TaskName $TaskName
Write-Host "LabTrack Monitoring Agent is now installed and running as SYSTEM." -ForegroundColor Cyan

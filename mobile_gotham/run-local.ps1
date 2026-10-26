param(
    [string]$DeviceId,
    [ValidateRange(1, 65535)]
    [int]$Port = 8000,
    [switch]$PrepareOnly
)

$ErrorActionPreference = 'Stop'
$adbCandidates = @(
    (Join-Path $env:LOCALAPPDATA 'Android\sdk\platform-tools\adb.exe')
)
if ($env:ANDROID_HOME) {
    $adbCandidates += Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
}
if ($env:ANDROID_SDK_ROOT) {
    $adbCandidates += Join-Path $env:ANDROID_SDK_ROOT 'platform-tools\adb.exe'
}
$adbPath = $adbCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $adbPath) {
    $adbPath = (Get-Command adb -ErrorAction Stop).Source
}

if (-not $DeviceId) {
    $deviceOutput = & $adbPath devices
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membaca perangkat Android.' }
    $connectedDevices = @($deviceOutput | Where-Object { $_ -match '^\S+\s+device\s*$' } | ForEach-Object { ($_ -split '\s+')[0] })
    if ($connectedDevices.Count -eq 0) {
        throw 'Belum ada perangkat Android siap. Nyalakan emulator, lalu jalankan kembali.'
    }
    if ($PrepareOnly) {
        foreach ($connectedDevice in $connectedDevices) {
            & $adbPath -s $connectedDevice reverse "tcp:$Port" "tcp:$Port"
            if ($LASTEXITCODE -ne 0) { throw "Gagal meneruskan port Laravel ke $connectedDevice." }
            Write-Output "Koneksi Laravel port $Port siap untuk $connectedDevice."
        }
        return
    }
    if ($connectedDevices.Count -ne 1) {
        throw 'Hubungkan satu perangkat Android, atau tentukan -DeviceId dari hasil adb devices.'
    }
    $DeviceId = $connectedDevices[0]
}

& $adbPath -s $DeviceId reverse "tcp:$Port" "tcp:$Port"
if ($LASTEXITCODE -ne 0) { throw 'Gagal meneruskan port Laravel ke Android.' }
if ($PrepareOnly) { return }

Push-Location $PSScriptRoot
try {
    & flutter run -d $DeviceId "--dart-define=API_BASE_URL=http://127.0.0.1:$Port/api/v1"
    if ($LASTEXITCODE -ne 0) { throw 'Flutter gagal dijalankan.' }
}
finally {
    Pop-Location
}

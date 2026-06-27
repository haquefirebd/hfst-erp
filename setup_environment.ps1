# PowerShell environment setup script for HFST ERP
Write-Host "Starting environment setup..." -ForegroundColor Green

# 1. Install PHP 8.3
Write-Host "Installing PHP 8.3 via winget..." -ForegroundColor Yellow
winget install PHP.PHP.8.3 --silent --accept-package-agreements --accept-source-agreements
if ($LASTEXITCODE -eq 0) {
    Write-Host "PHP 8.3 installed successfully." -ForegroundColor Green
} else {
    Write-Host "PHP 8.3 installation failed or already exists. Continuing..." -ForegroundColor Cyan
}

# 2. Install PostgreSQL 17
Write-Host "Installing PostgreSQL 17 via winget..." -ForegroundColor Yellow
winget install PostgreSQL.PostgreSQL.17 --silent --accept-package-agreements --accept-source-agreements --override "--mode unattended --superpassword postgres --serverport 5432"
if ($LASTEXITCODE -eq 0) {
    Write-Host "PostgreSQL 17 installed successfully." -ForegroundColor Green
} else {
    Write-Host "PostgreSQL 17 installation failed or already exists. Continuing..." -ForegroundColor Cyan
}

# 3. Download Composer Local Binary
Write-Host "Downloading Composer local binary..." -ForegroundColor Yellow
$composerPath = Join-Path $PSScriptRoot "composer.phar"
if (Test-Path $composerPath) {
    Write-Host "composer.phar already exists." -ForegroundColor Green
} else {
    Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile $composerPath
    Write-Host "Downloaded composer.phar successfully." -ForegroundColor Green
}

# 4. Refresh Environment variables
Write-Host "Refreshing Environment Path..." -ForegroundColor Yellow
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

Write-Host "Environment setup complete!" -ForegroundColor Green

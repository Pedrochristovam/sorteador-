param(
  [string]$Saida = "$PSScriptRoot\..\storage\telas",
  [string]$Prefixo = "antes",
  [int]$Largura = 1440,
  [int]$Altura = 1800,
  [string[]]$Somente = @()
)

$chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe"
New-Item -ItemType Directory -Force -Path $Saida | Out-Null

$ultimo = ""
$historico = Join-Path $PSScriptRoot "..\storage\sorteios_session.json"
if (Test-Path $historico) {
  $lista = Get-Content $historico -Raw | ConvertFrom-Json
  if ($lista) { $ultimo = ($lista | Sort-Object dataHora -Descending | Select-Object -First 1).id }
}

$paginas = [ordered]@{
  "dashboard"    = "page=dashboard"
  "empresas"     = "page=empresas"
  "empresa-form" = "page=empresa-form"
  "detalhe"      = "page=empresa-detalhe&id=EMP-001"
  "novo-sorteio" = "page=novo-sorteio&etapa=demanda"
  "historico"    = "page=historico"
  "auditoria"    = "page=auditoria"
  "juridico"     = "page=juridico-dashboard"
  "resultado"    = "page=resultado&id=$ultimo"
  "hist-detalhe" = "page=historico-detalhe&id=$ultimo"
}

foreach ($nome in $paginas.Keys) {
  if ($Somente.Count -gt 0 -and $Somente -notcontains $nome) { continue }
  $arquivo = Join-Path (Resolve-Path $Saida) "$Prefixo-$nome.png"
  $perfil = Join-Path $env:TEMP "sorteador-chrome-$nome"
  $url = "http://localhost:8080/index.php?" + $paginas[$nome]
  $argumentos = @("--headless=new", "--disable-gpu", "--hide-scrollbars", "--user-data-dir=$perfil",
            "--virtual-time-budget=6000", "--window-size=$Largura,$Altura", "--screenshot=$arquivo", $url)
  Start-Process -FilePath $chrome -ArgumentList $argumentos -Wait -NoNewWindow -RedirectStandardError (Join-Path $env:TEMP "chrome-$nome.log")
  $ok = Test-Path $arquivo
  Write-Output ("{0} -> {1}" -f $nome, $(if ($ok) { "ok" } else { "FALHOU" }))
}

# PowerShell: convert UTF-8 CSV to CP1250 (Windows-1250)
# Run in the folder containing the CSV. Adjust filenames as needed.
$inFile = 'example_szemelyek_cp1250_semicolon.csv'
$outFile = 'example_szemelyek_cp1250_semicolon_cp1250.csv'
$utf8 = Get-Content $inFile -Raw -Encoding UTF8
[System.IO.File]::WriteAllText($outFile, $utf8, [System.Text.Encoding]::GetEncoding(1250))
Write-Host "Wrote $outFile with CP1250 encoding"

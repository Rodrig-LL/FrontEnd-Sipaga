param([Parameter(Mandatory)][string]$Daftar, [string]$Bahasa = "en-US")
# Pembantu dpp_ocr.py: OCR bawaan Windows 10/11 (Windows.Media.Ocr), tanpa pemasangan apa pun.
# $Daftar = berkas teks berisi satu jalur gambar per baris. Untuk tiap gambar dicetak satu baris
# JSON: {"gambar": ..., "kata": [{"t": teks, "x":, "y":, "w":, "h":}, ...]}.
$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null = [Windows.Media.Ocr.OcrEngine, Windows.Foundation, ContentType = WindowsRuntime]
$null = [Windows.Graphics.Imaging.BitmapDecoder, Windows.Foundation, ContentType = WindowsRuntime]
$null = [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime]
$null = [Windows.Globalization.Language, Windows.Foundation, ContentType = WindowsRuntime]

$asTask = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object {
    $_.Name -eq "AsTask" -and $_.GetParameters().Count -eq 1 -and
    $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
function Tunggu($op, [Type]$tipe) {
    $t = $asTask.MakeGenericMethod($tipe).Invoke($null, @($op))
    $t.Wait() | Out-Null
    $t.Result
}

# Yang dibaca hanya angka dan huruf Latin, jadi bahasa apa pun yang terpasang memadai.
$mesin = $null
try { $mesin = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage([Windows.Globalization.Language]::new($Bahasa)) } catch {}
if (-not $mesin) { $mesin = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages() }
if (-not $mesin) {
    foreach ($b in [Windows.Media.Ocr.OcrEngine]::AvailableRecognizerLanguages) {
        $mesin = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage($b)
        if ($mesin) { break }
    }
}
if (-not $mesin) { [Console]::Error.WriteLine("TANPA_MESIN_OCR"); exit 3 }

foreach ($g in (Get-Content -LiteralPath $Daftar -Encoding UTF8 | Where-Object { $_.Trim() })) {
    $file = Tunggu ([Windows.Storage.StorageFile]::GetFileFromPathAsync([string]$g)) ([Windows.Storage.StorageFile])
    $strm = Tunggu ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
    $dek = Tunggu ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($strm)) ([Windows.Graphics.Imaging.BitmapDecoder])
    $bmp = Tunggu ($dek.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])
    $hasil = Tunggu ($mesin.RecognizeAsync($bmp)) ([Windows.Media.Ocr.OcrResult])
    $kata = foreach ($ln in $hasil.Lines) { foreach ($w in $ln.Words) {
        $r = $w.BoundingRect
        [ordered]@{ t = [string]$w.Text; x = [math]::Round($r.X, 1); y = [math]::Round($r.Y, 1)
                    w = [math]::Round($r.Width, 1); h = [math]::Round($r.Height, 1) } } }
    $strm.Dispose()
    [Console]::Out.WriteLine((@{ gambar = [string]$g; kata = @($kata) } | ConvertTo-Json -Compress -Depth 4))
}

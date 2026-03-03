$headers = @{ "Content-Type" = "application/json"; "Accept" = "application/json" }
$body = @{
    email = "demo@dev.com"
    password = "Demo1234!"
} | ConvertTo-Json

try {
    Write-Host "Logging in to http://127.0.0.1:8000/api/auth/login..."
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/login" -Method Post -Body $body -Headers $headers
    
    if ($response.token) {
        Write-Host "Login Successful. Token received."
        # Write-Host "Token: $($response.token)"
        
        Write-Host "Testing protected route /api/projects..."
        $headers["Authorization"] = "Bearer " + $response.token
        
        $projects = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/projects" -Method Get -Headers $headers
        Write-Host "Protected route success!"
        Write-Host "Found $($projects.data.Count) projects."
    } else {
        Write-Host "Login failed. No token received."
        $response | ConvertTo-Json
    }
} catch {
    Write-Host "Error occurred:"
    Write-Host $_.Exception.Message
    if ($_.Exception.Response) {
        Write-Host "Status Code: $($_.Exception.Response.StatusCode)"
        try {
            $stream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            Write-Host "Body: $($reader.ReadToEnd())"
        } catch {
            Write-Host "Could not read response body."
        }
    }
}

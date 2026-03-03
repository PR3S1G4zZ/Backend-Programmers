$headers = @{ "Content-Type" = "application/json"; "Accept" = "application/json" }
$body = @{
    email    = "demo@dev.com"
    password = "Demo1234!"
} | ConvertTo-Json

try {
    Write-Host "1. Testing Login..."
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/login" -Method Post -Body $body -Headers $headers
    
    if ($response.token) {
        Write-Host "Login Successful. Token received."
        
        Write-Host "2. Testing Database Access (Projects Route)..."
        $headers["Authorization"] = "Bearer " + $response.token
        
        $projects = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/projects" -Method Get -Headers $headers
        Write-Host "Success! Retrieved $($projects.data.Count) projects."
    }
    else {
        Write-Host "Login failed. No token."
        $response | ConvertTo-Json
    }
}
catch {
    Write-Host "Error occurred:"
    Write-Host $_.Exception.Message
    if ($_.Exception.Response) {
        Write-Host "Status Code: $($_.Exception.Response.StatusCode)"
    }
}

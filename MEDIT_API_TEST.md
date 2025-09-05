# Medit Link API Test Script

This document describes the Medit Link API test functionality implemented in the Dental Lab application.

## Overview

The Medit Link API test script provides comprehensive testing of API credentials to ensure they are properly configured and can connect to the Medit Link API services.

## Features

### 1. Command Line Testing
- **Command**: `php artisan medit:test`
- **Options**: `--credential-id=ID` (optional, tests specific credential)
- **Purpose**: Test API credentials from the command line

### 2. Web Interface Testing
- **Location**: API Credentials management page
- **Button**: Green "Test API Connection" button (WiFi icon)
- **Purpose**: Test API credentials through the web interface

## Test Components

### 1. Credential Format Validation
- ✅ Validates Client ID format and length
- ✅ Validates Client Secret format and length  
- ✅ Validates Base URL format (if provided)
- ✅ Checks for empty or invalid values

### 2. OAuth Endpoint Testing
- ✅ Tests connection to OAuth token endpoint
- ✅ Attempts client credentials grant (expected to fail for Medit Link)
- ✅ Validates endpoint accessibility
- ✅ Provides appropriate feedback for Medit Link's Authorization Code flow

### 3. API Connectivity Testing
- ✅ Tests actual API calls (if access token is available)
- ✅ Uses `/v1/user/me` endpoint for testing
- ✅ Validates authentication headers
- ✅ Displays API response data

## Usage

### Command Line
```bash
# Test all active Medit Link credentials
php artisan medit:test

# Test specific credential by ID
php artisan medit:test --credential-id=1
```

### Web Interface
1. Navigate to API Credentials page
2. Click the green "Test API Connection" button
3. View detailed results in the modal popup

## Test Results

The test provides detailed feedback on:

- **Credential Format**: Valid/Invalid with specific error messages
- **OAuth Endpoint**: Accessible/Not Accessible with status codes
- **API Connectivity**: Connected/Failed with response data
- **Overall Status**: Success/Failure with comprehensive details

## Medit Link API Specifics

### Authentication Flow
- Medit Link uses OAuth 2.0 Authorization Code flow
- Does NOT support Client Credentials grant
- Requires user authorization for access tokens

### Endpoints Tested
- **OAuth Token**: `https://openapi-auth.meditlink.com/oauth/token`
- **API Base**: `https://api.meditlink.com/v1/user/me`

### Expected Behavior
- OAuth endpoint should return 400 (Bad Request) for client credentials
- This is expected behavior for Medit Link's authorization flow
- API connectivity test only works with valid access tokens

## Error Handling

The test script handles various error scenarios:

- **Network Errors**: Connection timeouts, DNS resolution failures
- **SSL Errors**: Certificate validation issues (common in development)
- **Authentication Errors**: Invalid credentials, expired tokens
- **API Errors**: Invalid endpoints, rate limiting, server errors

## Development Notes

- SSL certificate errors are common in development environments
- Use proper SSL certificates in production
- Access tokens are required for full API testing
- Consider implementing OAuth authorization flow for complete testing

## Security Considerations

- Credentials are encrypted in the database
- Test requests use secure HTTPS connections
- No sensitive data is logged during testing
- CSRF protection is enforced on web requests

## Troubleshooting

### Common Issues
1. **SSL Certificate Errors**: Use `--insecure` flag or proper certificates
2. **Network Timeouts**: Check internet connectivity and firewall settings
3. **Invalid Credentials**: Verify Client ID and Secret are correct
4. **Missing Access Token**: Implement OAuth authorization flow

### Debug Mode
Enable detailed logging by setting `LOG_LEVEL=debug` in your `.env` file.

## Future Enhancements

- [ ] OAuth authorization flow implementation
- [ ] Token refresh functionality
- [ ] Automated periodic testing
- [ ] Email notifications for test failures
- [ ] Integration with monitoring systems

# Error Feature

Feature for handling errors in the application.

## Structure

```
error/
├── pages/
│   ├── not-found-page/          # 404 page
│   └── server-error-page/       # 5xx page
├── error.routes.ts              # Routing for error pages
├── error.routes.spec.ts         # Tests for routing
└── README.md                    # This documentation
```

## Error Pages

### 404 - Not Found Page
- **Path**: `/error/404`, `/error/not-found`
- **Component**: `NotFoundPageComponent`
- **Description**: Displayed when a user tries to access a non-existent page
- **Features**:
  - "Back to home page" button
  - "Go back" button (browser history)

### 500 - Server Error Page
- **Path**: `/error/500`, `/error/server-error`
- **Component**: `ServerErrorPageComponent`
- **Description**: Displayed when a server error occurs
- **Features**:
  - "Refresh page" button
  - "Back to home page" button
  - "Go back" button (browser history)

## Routing

### Wildcard Route
A wildcard route (`**`) was added in the main `app.routes.ts` file, which redirects all non-existent paths to the 404 page:

```typescript
{
  path: '**',
  redirectTo: 'error/404'
}
```

### Error Routes
```typescript
export const errorRoutes: Routes = [
  { path: '404', component: NotFoundPageComponent },
  { path: '500', component: ServerErrorPageComponent },
  { path: 'server-error', component: ServerErrorPageComponent },
  { path: 'not-found', component: NotFoundPageComponent }
];
```

## Design

### 404 Page
- **Color**: Blue gradient (#667eea → #764ba2)
- **Icon**: Checkmark in a circle
- **Error code**: 404

### 500 Page
- **Color**: Red gradient (#ff6b6b → #ee5a24)
- **Icon**: Warning in a circle
- **Error code**: 500

### Shared Features
- Responsive design
- Glassmorphism effect (backdrop-filter: blur)
- Hover animations on buttons
- SVG icons
- Mobile-first approach

## Usage

### Programmatic redirect to an error page

```typescript
import { Router } from '@angular/router';

constructor(private router: Router) {}

// Redirect to 404
this.router.navigate(['/error/404']);

// Redirect to 500
this.router.navigate(['/error/500']);
```

### Handling HTTP errors

```typescript
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';

// In an interceptor or service
if (error.status === 404) {
  this.router.navigate(['/error/404']);
} else if (error.status >= 500) {
  this.router.navigate(['/error/500']);
}
```

## Tests

Run tests for the error feature:

```bash
npm test -- --include="**/error/**/*.spec.ts"
```

## Compatibility

- Angular 17+
- Standalone components
- Modern CSS (backdrop-filter, CSS Grid, Flexbox)
- Responsive design (mobile, tablet, desktop)

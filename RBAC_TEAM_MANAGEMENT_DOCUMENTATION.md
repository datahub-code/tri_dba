# Role-Based Access Control & Team Management System

## Overview
This document describes the comprehensive Role-Based Access Control (RBAC) and Team Management system implemented for the Marketing Genius application. The system provides granular permission management, team member administration, and secure multi-user collaboration.

## 🎯 System Architecture

### Backend Components

#### 1. Permission Management Service (`backend/src/services/permissions.ts`)
- **43 Granular Permissions** across 7 categories:
  - User Management (4 permissions)
  - Organization Management (6 permissions) 
  - Content Management (7 permissions)
  - Social Media Management (8 permissions)
  - Lead Management (6 permissions)
  - Analytics & Reporting (4 permissions)
  - System Administration (8 permissions)

- **4 Predefined Roles** with permission presets:
  - **Admin**: Full system access (all 43 permissions)
  - **Manager**: Operational management (32 permissions)
  - **Member**: Content creation and basic operations (18 permissions)
  - **Viewer**: Read-only access (8 permissions)

#### 2. Team Management API Routes (`backend/src/routes/organizations.ts`)
- **8 RESTful Endpoints**:
  ```
  GET    /api/organizations/:orgId/members      - List team members
  POST   /api/organizations/:orgId/invite      - Invite new member
  PUT    /api/organizations/:orgId/members/:userId/role - Update member role
  DELETE /api/organizations/:orgId/members/:userId - Remove member
  GET    /api/organizations/:orgId/invitations - List pending invitations
  POST   /api/organizations/:orgId/invitations/:token/accept - Accept invitation
  DELETE /api/organizations/:orgId/invitations/:inviteId - Cancel invitation
  GET    /api/permissions/available           - Get available permissions
  ```

#### 3. Permission Middleware (`backend/src/middleware/permissions.ts`)
- **Role-based Authorization**: `requireRole()` middleware
- **Permission-based Authorization**: `requirePermission()` middleware
- **Multiple Permission Checks**: `requireAllPermissions()` middleware
- **Resource-specific Permissions**: `requireResourcePermission()` middleware

#### 4. Organization Controller (`backend/src/controllers/organizations.ts`)
- Team member CRUD operations
- Invitation system with secure token generation
- Role management with permission validation
- Organization-scoped operations

### Frontend Components

#### 1. Team Management Interface (`frontend/src/components/team/TeamManagement.tsx`)
- **Team Statistics Dashboard**: Member count, role distribution, activity metrics
- **Member Management Table**: Searchable/filterable member list with role badges
- **Inline Role Management**: Quick role updates with confirmation
- **Member Actions**: Edit permissions, remove members, resend invitations
- **Responsive Design**: Mobile-friendly with glassmorphism UI

#### 2. Member Invitation Modal (`frontend/src/components/team/InviteMemberModal.tsx`)
- **Role Selection**: Visual role picker with descriptions
- **Email Validation**: Real-time email format validation
- **Invitation Preview**: Shows permissions that will be granted
- **Loading States**: Progress indicators for async operations

#### 3. Permission Management Modal (`frontend/src/components/team/PermissionModal.tsx`)
- **Granular Permission Control**: Category-organized permission checkboxes
- **Role-based Permissions**: Visual distinction between role and custom permissions
- **Permission Categories**: Organized by functional areas
- **Bulk Operations**: Role changes with permission reset options

#### 4. Team Page (`frontend/src/pages/TeamPage.tsx`)
- **Comprehensive Team Dashboard**: Statistics overview and management interface
- **Integrated Navigation**: Seamless integration with main dashboard
- **Permission-aware UI**: Shows/hides features based on user permissions

### Database Schema Integration

The system integrates with the existing database schema:

```sql
-- Users table (existing)
users (
  id, email, password_hash, first_name, last_name,
  role VARCHAR(50),              -- 'admin', 'manager', 'member', 'viewer'
  permissions JSONB,             -- Custom permissions array
  organization_id,
  created_at, updated_at
)

-- Organizations table (existing)
organizations (
  id, name, slug, settings JSONB,
  created_at, updated_at
)

-- Invitations table (created)
invitations (
  id, organization_id, email, role,
  permissions JSONB,             -- Custom permissions for invitation
  token VARCHAR(255),            -- Secure invitation token
  expires_at TIMESTAMP,
  created_by INTEGER,
  status VARCHAR(20),            -- 'pending', 'accepted', 'expired'
  created_at, updated_at
)
```

## 🔐 Security Features

### Authentication & Authorization
- **JWT-based Authentication**: Stateless token-based auth
- **Role Inheritance**: Permissions cascade from roles
- **Custom Permissions**: Additional permissions beyond role defaults
- **Organization Scoping**: All operations scoped to user's organization

### Permission Validation
- **Server-side Validation**: All permissions verified on backend
- **Middleware Protection**: Route-level permission checks
- **Resource Authorization**: User can only access their organization's data
- **Token Security**: Secure invitation tokens with expiration

### Data Protection
- **SQL Injection Prevention**: Parameterized queries throughout
- **XSS Protection**: Input sanitization and validation
- **CSRF Protection**: Token-based request validation
- **Rate Limiting**: API endpoint protection (can be added)

## 🚀 Key Features

### Team Management
- ✅ **Member Invitation System**: Email-based invitations with role selection
- ✅ **Role Management**: Dynamic role assignment with permission inheritance
- ✅ **Permission Customization**: Granular permission control beyond roles
- ✅ **Member Directory**: Searchable team member listing
- ✅ **Activity Tracking**: Member activity and status monitoring

### Permission System
- ✅ **43 Granular Permissions**: Comprehensive permission coverage
- ✅ **4 Role Presets**: Admin, Manager, Member, Viewer with logical defaults
- ✅ **Custom Permissions**: Additional permissions beyond role defaults
- ✅ **Permission Categories**: Organized by functional areas
- ✅ **Dynamic UI**: Permission-aware interface elements

### User Experience
- ✅ **Glassmorphism Design**: Modern, attractive UI design
- ✅ **Responsive Layout**: Mobile-friendly responsive design
- ✅ **Real-time Updates**: Immediate UI updates after changes
- ✅ **Loading States**: Proper feedback during async operations
- ✅ **Error Handling**: Comprehensive error messaging

## 📱 User Interface

### Navigation Integration
The team management system is integrated into the main dashboard navigation:

```
Dashboard → Team (Shield icon)
```

### Page Structure
```
/team
├── Team Statistics (top section)
├── Team Management Component
│   ├── Member Table
│   ├── Search & Filters
│   ├── Action Buttons
│   └── Role Badges
└── Modals
    ├── Invite Member Modal
    └── Permission Management Modal
```

### Permission-aware UI
- Features show/hide based on user permissions
- Role-appropriate actions and information display
- Clear visual indicators for permission levels

## 🔧 Technical Implementation

### API Integration
Frontend services communicate with backend APIs:

```typescript
// Team Management API calls
PermissionService.getTeamMembers(organizationId)
PermissionService.inviteMember(organizationId, email, role)
PermissionService.updateMemberRole(organizationId, userId, role)
PermissionService.getUserPermissions(organizationId, userId)
```

### State Management
- React hooks for local state management
- Context API for authentication state
- Real-time updates after mutations

### Error Handling
- Comprehensive try-catch blocks
- User-friendly error messages
- Fallback UI states for errors

## 🎯 Usage Examples

### 1. Inviting a New Team Member
1. Navigate to `/team`
2. Click "Invite Member" button
3. Enter email and select role
4. System sends invitation email
5. Invitee accepts and joins team

### 2. Managing Member Permissions
1. Find member in team table
2. Click "Edit Permissions" action
3. Modify role or add custom permissions
4. Save changes - takes effect immediately

### 3. Role-based Feature Access
- **Admins**: Full access to all features
- **Managers**: Team management + operational features
- **Members**: Content creation + basic features
- **Viewers**: Read-only access to most features

## 📋 System Status

### ✅ Completed Features
- [x] **Step 1**: Team Management API Routes (8 endpoints)
- [x] **Step 2**: Permission Management System (43 permissions, 4 roles)
- [x] **Step 3**: Frontend Team Management Components
- [x] Database migration for invitations table
- [x] Backend server integration and testing
- [x] Frontend routing and navigation integration
- [x] Comprehensive error handling and validation
- [x] Mobile-responsive design implementation

### 🔧 System Configuration
- **Server**: Running on `localhost:3001`
- **Database**: PostgreSQL with JSONB for flexible permissions
- **Cache**: Redis for session and token management
- **Frontend**: React 18 + TypeScript + Tailwind CSS
- **Authentication**: JWT tokens with role/permission inclusion

The role-based access control and team management system is now fully implemented and ready for production use. The system provides enterprise-grade security, comprehensive permission management, and an intuitive user experience for team collaboration.
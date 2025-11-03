# Marketing Genius - Database Schema Design

## Database Architecture Overview

The platform uses a **multi-database approach** optimized for different data types and access patterns:

- **PostgreSQL**: Relational data (users, subscriptions, CRM, analytics)
- **MongoDB**: Document-based data (content, messages, logs)
- **Redis**: Caching and session management
- **InfluxDB**: Time-series data (metrics, analytics)
- **Elasticsearch**: Search and full-text indexing

## 1. PostgreSQL Schema (Primary Relational Data)

### 1.1 User Management Tables

```sql
-- Organizations/Companies
CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    industry VARCHAR(100),
    size_category VARCHAR(50), -- 'solo', 'small', 'medium', 'large'
    country_code VARCHAR(3),
    timezone VARCHAR(50),
    settings JSONB DEFAULT '{}',
    subscription_id UUID,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Users
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    email VARCHAR(320) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role VARCHAR(50) DEFAULT 'user', -- 'owner', 'admin', 'user', 'viewer'
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'suspended'
    last_login TIMESTAMP WITH TIME ZONE,
    email_verified BOOLEAN DEFAULT FALSE,
    phone VARCHAR(20),
    avatar_url TEXT,
    preferences JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- User Sessions
CREATE TABLE user_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    device_info JSONB,
    ip_address INET,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 1.2 Subscription & Billing Tables

```sql
-- Subscription Plans
CREATE TABLE subscription_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    price_monthly DECIMAL(10,2) NOT NULL,
    price_annual DECIMAL(10,2) NOT NULL,
    features JSONB NOT NULL, -- Feature limits and permissions
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Subscriptions
CREATE TABLE subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    plan_id UUID REFERENCES subscription_plans(id),
    status VARCHAR(20) DEFAULT 'active', -- 'trial', 'active', 'cancelled', 'expired'
    billing_cycle VARCHAR(20), -- 'monthly', 'annual'
    current_period_start TIMESTAMP WITH TIME ZONE,
    current_period_end TIMESTAMP WITH TIME ZONE,
    trial_start TIMESTAMP WITH TIME ZONE,
    trial_end TIMESTAMP WITH TIME ZONE,
    cancelled_at TIMESTAMP WITH TIME ZONE,
    stripe_subscription_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Usage Tracking
CREATE TABLE usage_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    metric_name VARCHAR(100) NOT NULL, -- 'social_accounts', 'leads', 'ai_content', etc.
    metric_value INTEGER NOT NULL,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(organization_id, metric_name, billing_period_start)
);

-- Invoices
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    subscription_id UUID REFERENCES subscriptions(id),
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'paid', 'failed', 'cancelled'
    due_date DATE,
    paid_at TIMESTAMP WITH TIME ZONE,
    stripe_invoice_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 1.3 Social Media Management Tables

```sql
-- Social Media Accounts
CREATE TABLE social_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    platform VARCHAR(50) NOT NULL, -- 'facebook', 'instagram', 'linkedin', 'twitter', 'tiktok'
    account_name VARCHAR(255) NOT NULL,
    account_id VARCHAR(255) NOT NULL, -- Platform-specific account ID
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP WITH TIME ZONE,
    account_metadata JSONB DEFAULT '{}',
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'expired', 'error'
    last_sync TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(organization_id, platform, account_id)
);

-- Content Templates
CREATE TABLE content_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    template_content TEXT NOT NULL,
    variables JSONB DEFAULT '[]', -- List of variables in template
    platforms VARCHAR(100)[], -- Supported platforms
    is_ai_generated BOOLEAN DEFAULT FALSE,
    usage_count INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Marketing Campaigns
CREATE TABLE marketing_campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    campaign_type VARCHAR(50) NOT NULL, -- 'social_media', 'email', 'sms', 'multi_channel'
    status VARCHAR(20) DEFAULT 'draft', -- 'draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'
    start_date TIMESTAMP WITH TIME ZONE,
    end_date TIMESTAMP WITH TIME ZONE,
    budget DECIMAL(10,2),
    target_audience JSONB DEFAULT '{}',
    goals JSONB DEFAULT '{}', -- CTR, conversions, reach, etc.
    platforms VARCHAR(50)[], -- ['facebook', 'instagram', 'linkedin']
    tags VARCHAR(100)[],
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Scheduled Posts
CREATE TABLE scheduled_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    campaign_id UUID REFERENCES marketing_campaigns(id) ON DELETE SET NULL,
    social_account_id UUID REFERENCES social_accounts(id) ON DELETE CASCADE,
    title VARCHAR(500),
    content TEXT NOT NULL,
    media_urls TEXT[],
    hashtags VARCHAR(500)[],
    scheduled_time TIMESTAMP WITH TIME ZONE NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled', -- 'scheduled', 'published', 'failed', 'cancelled'
    platform_post_id VARCHAR(255), -- ID returned by platform after publishing
    error_message TEXT,
    published_at TIMESTAMP WITH TIME ZONE,
    engagement_metrics JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 1.4 CRM & Lead Management Tables

```sql
-- Contacts
CREATE TABLE contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(320),
    phone VARCHAR(20),
    company VARCHAR(255),
    job_title VARCHAR(255),
    country VARCHAR(100),
    city VARCHAR(100),
    lead_source VARCHAR(100), -- 'social_media', 'website', 'referral', 'trade_inquiry'
    lead_score INTEGER DEFAULT 0,
    status VARCHAR(50) DEFAULT 'new', -- 'new', 'qualified', 'contacted', 'converted', 'lost'
    tags VARCHAR(100)[],
    custom_fields JSONB DEFAULT '{}',
    notes TEXT,
    last_contacted TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Deals/Opportunities
CREATE TABLE deals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    value DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'USD',
    stage VARCHAR(100) NOT NULL, -- Customizable pipeline stages
    probability INTEGER DEFAULT 0, -- 0-100
    expected_close_date DATE,
    actual_close_date DATE,
    source VARCHAR(100),
    assigned_user_id UUID REFERENCES users(id),
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Communication History
CREATE TABLE communications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    contact_id UUID REFERENCES contacts(id) ON DELETE CASCADE,
    deal_id UUID REFERENCES deals(id) ON DELETE SET NULL,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    type VARCHAR(50) NOT NULL, -- 'email', 'call', 'meeting', 'note', 'sms', 'whatsapp'
    direction VARCHAR(20), -- 'inbound', 'outbound'
    subject VARCHAR(500),
    content TEXT,
    metadata JSONB DEFAULT '{}', -- Platform-specific data
    scheduled_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 1.5 Trade & Marketplace Tables

```sql
-- Business Profiles
CREATE TABLE business_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    business_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100), -- 'manufacturer', 'supplier', 'trader', 'service_provider'
    industry_categories VARCHAR(100)[],
    description TEXT,
    website_url TEXT,
    established_year INTEGER,
    employee_count_range VARCHAR(50),
    annual_revenue_range VARCHAR(50),
    certifications VARCHAR(100)[],
    languages VARCHAR(50)[],
    trade_regions VARCHAR(100)[], -- Countries/regions they trade with
    verification_status VARCHAR(50) DEFAULT 'unverified', -- 'unverified', 'basic', 'premium', 'enterprise'
    verification_documents JSONB DEFAULT '[]',
    business_license_number VARCHAR(255),
    tax_id VARCHAR(255),
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Trade Opportunities
CREATE TABLE trade_opportunities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'buy', 'sell', 'service'
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100),
    subcategory VARCHAR(100),
    quantity VARCHAR(100),
    unit_price DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'USD',
    min_order_quantity VARCHAR(100),
    delivery_terms VARCHAR(255),
    payment_terms VARCHAR(255),
    valid_until DATE,
    target_countries VARCHAR(100)[],
    status VARCHAR(50) DEFAULT 'active', -- 'active', 'paused', 'closed', 'expired'
    views_count INTEGER DEFAULT 0,
    inquiries_count INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Trade Inquiries
CREATE TABLE trade_inquiries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    opportunity_id UUID REFERENCES trade_opportunities(id) ON DELETE CASCADE,
    inquirer_organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    contact_details JSONB NOT NULL,
    status VARCHAR(50) DEFAULT 'new', -- 'new', 'responded', 'negotiating', 'closed'
    response_message TEXT,
    responded_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 1.6 Analytics & Reporting Tables

```sql
-- Campaign Performance (Aggregated Daily)
CREATE TABLE campaign_performance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    campaign_id UUID REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
    social_account_id UUID REFERENCES social_accounts(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    platform VARCHAR(50) NOT NULL,
    impressions INTEGER DEFAULT 0,
    reach INTEGER DEFAULT 0,
    engagement INTEGER DEFAULT 0,
    clicks INTEGER DEFAULT 0,
    shares INTEGER DEFAULT 0,
    comments INTEGER DEFAULT 0,
    likes INTEGER DEFAULT 0,
    saves INTEGER DEFAULT 0,
    followers_gained INTEGER DEFAULT 0,
    conversions INTEGER DEFAULT 0,
    cost_per_click DECIMAL(8,4) DEFAULT 0,
    cost_per_conversion DECIMAL(8,4) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(organization_id, campaign_id, social_account_id, date, platform)
);

-- Lead Source Performance
CREATE TABLE lead_source_performance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    source VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    leads_generated INTEGER DEFAULT 0,
    leads_qualified INTEGER DEFAULT 0,
    leads_converted INTEGER DEFAULT 0,
    total_value DECIMAL(15,2) DEFAULT 0,
    avg_conversion_time INTEGER, -- in days
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(organization_id, source, date)
);
```

### 1.7 AI & Automation Tables

```sql
-- AI Generated Content
CREATE TABLE ai_content (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    content_type VARCHAR(50) NOT NULL, -- 'post', 'caption', 'hashtags', 'email'
    prompt TEXT NOT NULL,
    generated_content TEXT NOT NULL,
    platform VARCHAR(50),
    tone VARCHAR(50), -- 'professional', 'casual', 'friendly', 'formal'
    language VARCHAR(10) DEFAULT 'en',
    model_used VARCHAR(100), -- 'gpt-4', 'claude', etc.
    tokens_used INTEGER,
    rating INTEGER, -- 1-5 user rating
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Admin Users
CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(320) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role VARCHAR(50) NOT NULL, -- 'super_admin', 'platform_admin', 'customer_success_admin', 'support_admin'
    permissions JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Admin Activity Log
CREATE TABLE admin_activity_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL, -- 'user_update', 'organization_suspend', 'system_maintenance'
    resource_type VARCHAR(50), -- 'user', 'organization', 'system'
    resource_id VARCHAR(255),
    details JSONB DEFAULT '{}',
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Support Tickets
CREATE TABLE support_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    assigned_admin_id UUID REFERENCES admin_users(id) ON DELETE SET NULL,
    subject VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'urgent'
    status VARCHAR(20) DEFAULT 'open', -- 'open', 'in_progress', 'waiting', 'resolved', 'closed'
    category VARCHAR(100), -- 'billing', 'technical', 'feature_request', 'bug_report'
    satisfaction_rating INTEGER, -- 1-5 rating after resolution
    resolved_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- System Announcements
CREATE TABLE system_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'maintenance', 'feature', 'security', 'general'
    priority VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high'
    target_audience VARCHAR(50) DEFAULT 'all_users', -- 'all_users', 'paid_users', 'trial_users', 'specific_plan'
    target_plan VARCHAR(50), -- 'starter', 'professional', 'enterprise' (if target_audience is 'specific_plan')
    scheduled_time TIMESTAMP WITH TIME ZONE,
    sent_at TIMESTAMP WITH TIME ZONE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Platform Health Metrics
CREATE TABLE platform_health_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    metric_name VARCHAR(100) NOT NULL, -- 'api_response_time', 'error_rate', 'active_users'
    metric_value DECIMAL(15,4) NOT NULL,
    metric_unit VARCHAR(20), -- 'ms', 'percent', 'count'
    service_name VARCHAR(100), -- 'api_gateway', 'database', 'redis'
    recorded_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Hashtag Analytics
CREATE TABLE hashtag_analytics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    hashtag VARCHAR(200) NOT NULL,
    platform VARCHAR(50) NOT NULL,
    usage_count INTEGER DEFAULT 0,
    reach INTEGER DEFAULT 0,
    engagement INTEGER DEFAULT 0,
    trending_score DECIMAL(8,4) DEFAULT 0,
    date DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(organization_id, hashtag, platform, date)
);

-- Trend Monitoring
CREATE TABLE trend_monitoring (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    trend_keyword VARCHAR(200) NOT NULL,
    industry VARCHAR(100),
    platform VARCHAR(50) NOT NULL,
    trend_score DECIMAL(8,4) DEFAULT 0,
    volume INTEGER DEFAULT 0,
    sentiment VARCHAR(20), -- 'positive', 'negative', 'neutral'
    is_tracking BOOLEAN DEFAULT TRUE,
    alert_threshold DECIMAL(8,4) DEFAULT 50.0,
    detected_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Influencer Profiles
CREATE TABLE influencer_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    platform VARCHAR(50) NOT NULL,
    platform_user_id VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    bio TEXT,
    followers_count INTEGER DEFAULT 0,
    following_count INTEGER DEFAULT 0,
    posts_count INTEGER DEFAULT 0,
    engagement_rate DECIMAL(8,4) DEFAULT 0,
    average_likes INTEGER DEFAULT 0,
    average_comments INTEGER DEFAULT 0,
    category VARCHAR(100), -- 'fashion', 'tech', 'lifestyle', etc.
    location VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE,
    profile_image_url TEXT,
    last_updated TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(platform, platform_user_id)
);

-- Influencer Collaborations
CREATE TABLE influencer_collaborations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    campaign_id UUID REFERENCES marketing_campaigns(id) ON DELETE SET NULL,
    influencer_id UUID REFERENCES influencer_profiles(id) ON DELETE CASCADE,
    collaboration_type VARCHAR(50) NOT NULL, -- 'sponsored_post', 'story', 'reel', 'collaboration'
    status VARCHAR(50) DEFAULT 'proposed', -- 'proposed', 'accepted', 'in_progress', 'completed', 'cancelled'
    budget DECIMAL(10,2),
    deliverables JSONB DEFAULT '{}',
    deadline DATE,
    content_guidelines TEXT,
    approval_status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'revision_requested'
    performance_metrics JSONB DEFAULT '{}',
    contract_signed BOOLEAN DEFAULT FALSE,
    payment_status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'paid', 'failed'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Social Media Engagement Tracking
CREATE TABLE social_engagement_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    social_account_id UUID REFERENCES social_accounts(id) ON DELETE CASCADE,
    post_id UUID REFERENCES scheduled_posts(id) ON DELETE CASCADE,
    platform_post_id VARCHAR(255) NOT NULL,
    engagement_type VARCHAR(50) NOT NULL, -- 'like', 'comment', 'share', 'save', 'click'
    user_id VARCHAR(255), -- Platform-specific user ID
    username VARCHAR(255),
    content TEXT, -- Comment content if applicable
    timestamp TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Automation Rules
CREATE TABLE automation_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_type VARCHAR(100) NOT NULL, -- 'new_lead', 'social_mention', 'email_received', 'engagement_threshold'
    trigger_conditions JSONB NOT NULL,
    action_type VARCHAR(100) NOT NULL, -- 'send_email', 'create_task', 'update_lead_score', 'post_content'
    action_config JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    run_count INTEGER DEFAULT 0,
    last_run TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

## 2. MongoDB Collections (Document Store)

### 2.1 Content Management
```javascript
// content_library
{
    _id: ObjectId,
    organizationId: UUID,
    type: "image" | "video" | "document" | "template",
    fileName: String,
    originalName: String,
    mimeType: String,
    fileSize: Number,
    url: String,
    thumbnailUrl: String,
    metadata: {
        width: Number,
        height: Number,
        duration: Number, // for videos
        tags: [String],
        description: String
    },
    uploadedBy: UUID,
    createdAt: Date,
    updatedAt: Date
}

// message_templates
{
    _id: ObjectId,
    organizationId: UUID,
    name: String,
    type: "email" | "sms" | "whatsapp" | "social_message",
    subject: String,
    content: String,
    variables: [String],
    category: String,
    language: String,
    isActive: Boolean,
    usageCount: Number,
    createdAt: Date,
    updatedAt: Date
}
```

### 2.2 Communication Logs
```javascript
// message_logs
{
    _id: ObjectId,
    organizationId: UUID,
    contactId: UUID,
    type: "email" | "sms" | "whatsapp" | "call",
    direction: "inbound" | "outbound",
    subject: String,
    content: String,
    status: "sent" | "delivered" | "read" | "failed",
    metadata: {
        messageId: String,
        providerId: String,
        deliveredAt: Date,
        readAt: Date,
        errorMessage: String
    },
    sentAt: Date,
    createdAt: Date
}

// social_interactions
{
    _id: ObjectId,
    organizationId: UUID,
    socialAccountId: UUID,
    platform: String,
    interactionType: "comment" | "like" | "share" | "mention" | "dm",
    postId: String,
    fromUser: {
        id: String,
        name: String,
        handle: String
    },
    content: String,
    sentiment: "positive" | "negative" | "neutral",
    isResponded: Boolean,
    responseContent: String,
    respondedAt: Date,
    createdAt: Date
}
```

## 3. Redis Cache Structure

### 3.1 Session Management
```
user:session:{sessionToken} -> {userId, organizationId, expiresAt}
user:{userId}:sessions -> Set of active session tokens
```

### 3.2 Feature Limits Tracking
```
org:{orgId}:usage:{metric}:{month} -> current usage count
org:{orgId}:limits -> plan limits (cached from DB)
```

### 3.3 Social Media Rate Limiting
```
social:ratelimit:{platform}:{accountId} -> request count and reset time
```

### 3.4 Real-time Analytics
```
analytics:realtime:{orgId}:{date} -> daily aggregated metrics
dashboard:cache:{orgId} -> cached dashboard data
```

## 4. InfluxDB Time-Series Schema

### 4.1 Social Media Metrics
```
measurement: social_metrics
tags:
  - organization_id
  - platform
  - account_id
  - post_type
fields:
  - impressions (integer)
  - reach (integer)
  - engagement (integer)
  - clicks (integer)
  - conversion_rate (float)
time: timestamp
```

### 4.2 System Performance Metrics
```
measurement: system_performance
tags:
  - service_name
  - endpoint
  - status_code
fields:
  - response_time (float)
  - request_count (integer)
  - error_rate (float)
time: timestamp
```

## 5. Elasticsearch Indices

### 5.1 Search Index
```json
{
  "mappings": {
    "properties": {
      "organization_id": {"type": "keyword"},
      "type": {"type": "keyword"},
      "title": {
        "type": "text",
        "analyzer": "standard"
      },
      "content": {
        "type": "text",
        "analyzer": "standard"
      },
      "tags": {"type": "keyword"},
      "created_at": {"type": "date"},
      "metadata": {"type": "object"}
    }
  }
}
```

## 6. Database Optimization Strategies

### 6.1 Indexing Strategy
```sql
-- User lookup optimization
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_org_role ON users(organization_id, role);

-- Social media optimization
CREATE INDEX idx_social_accounts_org_platform ON social_accounts(organization_id, platform);
CREATE INDEX idx_scheduled_posts_time_status ON scheduled_posts(scheduled_time, status);

-- CRM optimization
CREATE INDEX idx_contacts_org_status ON contacts(organization_id, status);
CREATE INDEX idx_deals_org_stage ON deals(organization_id, stage);
CREATE INDEX idx_communications_contact_type ON communications(contact_id, type);

-- Analytics optimization
CREATE INDEX idx_campaign_perf_org_date ON campaign_performance(organization_id, date DESC);
CREATE INDEX idx_lead_source_perf_org_date ON lead_source_performance(organization_id, date DESC);
```

### 6.2 Partitioning Strategy
```sql
-- Partition large tables by month
CREATE TABLE campaign_performance_y2024m01 PARTITION OF campaign_performance
FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition logs by organization for better isolation
CREATE TABLE communications_org_large PARTITION OF communications
FOR VALUES IN (SELECT id FROM organizations WHERE size_category = 'large');
```

### 6.3 Data Retention Policies
- **Analytics data**: Keep detailed data for 2 years, aggregated data for 7 years
- **Communication logs**: Keep for 3 years (compliance requirement)
- **Social media posts**: Keep indefinitely (business records)
- **Usage tracking**: Keep for billing period + 7 years
- **AI content**: Keep for 1 year (for improvement purposes)

## 7. Data Migration & Backup Strategy

### 7.1 Backup Schedule
- **PostgreSQL**: Daily full backup + continuous WAL archiving
- **MongoDB**: Daily backup with point-in-time recovery
- **Redis**: Daily RDB snapshots + AOF
- **InfluxDB**: Daily backup with retention policy

### 7.2 Cross-Region Replication
- Primary region: US-East-1
- Secondary region: EU-West-1 (read replicas)
- Disaster recovery: Asia-Pacific-1

This database schema is designed to scale horizontally while maintaining data consistency and supporting the complex relationships required for a comprehensive marketing and trade platform.
<?php

namespace App\Constants;

class TenancyPermissionConstants
{
    public const TENANCY_PERMISSION_PREFIX = 'tenancy:';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const TENANT_CREATOR_ROLE = self::ROLE_USER;

    public const PERMISSION_CREATE_SUBSCRIPTIONS = 'tenancy: create subscriptions';

    public const PERMISSION_UPDATE_SUBSCRIPTIONS = 'tenancy: update subscriptions';

    public const PERMISSION_DELETE_SUBSCRIPTIONS = 'tenancy: delete subscriptions';

    public const PERMISSION_VIEW_SUBSCRIPTIONS = 'tenancy: view subscriptions';

    public const PERMISSION_CREATE_ORDERS = 'tenancy: create orders';

    public const PERMISSION_UPDATE_ORDERS = 'tenancy: update orders';

    public const PERMISSION_DELETE_ORDERS = 'tenancy: delete orders';

    public const PERMISSION_VIEW_ORDERS = 'tenancy: view orders';

    public const PERMISSION_VIEW_TRANSACTIONS = 'tenancy: view transactions';

    public const PERMISSION_INVITE_MEMBERS = 'tenancy: invite members';

    public const PERMISSION_MANAGE_TEAM = 'tenancy: manage team';

    public const PERMISSION_UPDATE_TENANT_SETTINGS = 'tenancy: update tenant settings';

    public const PERMISSION_VIEW_ROLES = 'tenancy: view roles';

    public const PERMISSION_CREATE_ROLES = 'tenancy: create roles';

    public const PERMISSION_UPDATE_ROLES = 'tenancy: update roles';

    public const PERMISSION_DELETE_ROLES = 'tenancy: delete roles';

    // Firmeninhaber (Company Owner) Role & Permissions
    public const ROLE_COMPANY_OWNER = 'company_owner';

    public const PERMISSION_CREATE_COMPANY = 'tenancy: create company';

    public const PERMISSION_VIEW_OWN_COMPANY = 'tenancy: view own company';

    public const PERMISSION_UPDATE_OWN_COMPANY = 'tenancy: update own company';

    public const PERMISSION_DELETE_OWN_COMPANY = 'tenancy: delete own company';

    public const PERMISSION_MANAGE_OWN_REVIEWS = 'tenancy: manage own reviews';

    public const PERMISSION_UPLOAD_COMPANY_IMAGES = 'tenancy: upload company images';

    public const PERMISSION_MANAGE_COMPANIES = 'tenancy: manage companies';

    public const PERMISSION_MANAGE_CLAIMS = 'tenancy: manage claims';
}

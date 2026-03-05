import { useState } from 'react';
import MethodCard, { type MethodDef } from '../components/MethodCard';
import JsonViewer from '../components/JsonViewer';

// ─── Method Definitions ──────────────────────────────────

const usersMethods: MethodDef[] = [
  {
    action: 'users.list',
    label: 'List Users',
    description: 'GET /users — paginated user list',
    httpMethod: 'GET',
    fields: [
      { name: 'limit', label: 'Limit', type: 'number', placeholder: '10' },
      { name: 'after', label: 'After (cursor)', type: 'text', placeholder: 'user_01H...' },
      { name: 'external_id', label: 'External ID filter', type: 'text', placeholder: 'my-user-123' },
    ],
  },
  {
    action: 'users.create',
    label: 'Create User',
    description: 'POST /users — create a new user',
    httpMethod: 'POST',
    fields: [
      { name: 'email', label: 'Email', type: 'text', placeholder: 'user@example.com' },
      { name: 'first_name', label: 'First Name', type: 'text', placeholder: 'Jane' },
      { name: 'last_name', label: 'Last Name', type: 'text', placeholder: 'Doe' },
      { name: 'external_id', label: 'External ID', type: 'text', placeholder: 'my-user-123' },
      { name: 'role', label: 'Role', type: 'text', placeholder: 'member' },
    ],
  },
  {
    action: 'users.retrieve',
    label: 'Retrieve User',
    description: 'GET /users/{id} — get a user by ID',
    httpMethod: 'GET',
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'users.update',
    label: 'Update User',
    description: 'PATCH /users/{id} — update user fields',
    httpMethod: 'PATCH',
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
      { name: 'email', label: 'Email', type: 'text', placeholder: 'new@example.com' },
      { name: 'first_name', label: 'First Name', type: 'text' },
      { name: 'last_name', label: 'Last Name', type: 'text' },
      { name: 'role', label: 'Role', type: 'text', placeholder: 'admin' },
    ],
  },
  {
    action: 'users.del',
    label: 'Delete User',
    description: 'DELETE /users/{id} — permanently delete a user',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'users.getOrCreate',
    label: 'Get or Create User',
    description: 'PUT /users/external/{id} — idempotent: find by external ID or create',
    httpMethod: 'PUT',
    fields: [
      { name: 'external_id', label: 'External ID', type: 'text', required: true, placeholder: 'my-user-123' },
      { name: 'email', label: 'Email', type: 'text', placeholder: 'user@example.com' },
      { name: 'first_name', label: 'First Name', type: 'text' },
      { name: 'last_name', label: 'Last Name', type: 'text' },
      { name: 'role', label: 'Role', type: 'text', placeholder: 'member' },
    ],
  },
  {
    action: 'users.removeExternalId',
    label: 'Remove External ID',
    description: 'DELETE /users/external/{id} — unlink an external ID from its user',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'external_id', label: 'External ID', type: 'text', required: true, placeholder: 'my-user-123' },
    ],
  },
];

const embedMethods: MethodDef[] = [
  {
    action: 'embed.createSession',
    label: 'Create Session',
    description: 'POST /embed/sessions — create an embed session for a user',
    httpMethod: 'POST',
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
      { name: 'origin', label: 'Origin', type: 'text', placeholder: 'https://example.com' },
      { name: 'ttl', label: 'TTL (seconds)', type: 'number', placeholder: '3600' },
    ],
  },
  {
    action: 'embed.refreshSession',
    label: 'Refresh Session',
    description: 'POST /embed/sessions/refresh — refresh an expiring session',
    httpMethod: 'POST',
    fields: [
      { name: 'session_token', label: 'Session Token', type: 'text', required: true, placeholder: 'es_...' },
    ],
  },
  {
    action: 'embed.listSessions',
    label: 'List Sessions',
    description: 'GET /embed/sessions — list active embed sessions',
    httpMethod: 'GET',
    fields: [
      { name: 'limit', label: 'Limit', type: 'number', placeholder: '100' },
    ],
  },
  {
    action: 'embed.revokeSession',
    label: 'Revoke Session',
    description: 'DELETE /embed/sessions/{id} — revoke a single session',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'session_id', label: 'Session ID', type: 'text', required: true, placeholder: 'es_...' },
    ],
  },
  {
    action: 'embed.revokeUserSessions',
    label: 'Revoke User Sessions',
    description: 'Revoke ALL sessions for a user (iterates and deletes)',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'embed.userProjects',
    label: 'List User Projects (FGA-filtered)',
    description: 'Creates an embed session, then calls the internal API to list only projects the user has access to via FGA permissions',
    httpMethod: 'GET',
    endpoint: '/api/user-projects.php',
    flatBody: true,
    fields: [
      { name: 'external_id', label: 'External ID', type: 'text', required: true, placeholder: 'my-user-123' },
      { name: 'email', label: 'Email', type: 'text', placeholder: 'user@example.com' },
      { name: 'first_name', label: 'First Name', type: 'text' },
      { name: 'last_name', label: 'Last Name', type: 'text' },
    ],
  },
];

const policiesMethods: MethodDef[] = [
  {
    action: 'policies.list',
    label: 'List Policies',
    description: 'GET /access/policies — list access policies',
    httpMethod: 'GET',
    fields: [
      { name: 'name', label: 'Name filter', type: 'text', placeholder: 'my-policy' },
    ],
  },
  {
    action: 'policies.create',
    label: 'Create Policy',
    description: 'POST /access/policies — create a new access policy',
    httpMethod: 'POST',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'sales-reps-policy' },
      { name: 'description', label: 'Description', type: 'text', placeholder: 'Policy for sales reps' },
      { name: 'source_ids', label: 'Source IDs (JSON array)', type: 'textarea', placeholder: '["src_abc123"]' },
      { name: 'row_filters', label: 'Row Filters (JSON array)', type: 'textarea', placeholder: '[{"column":"region","values":["US"]}]' },
    ],
  },
  {
    action: 'policies.retrieve',
    label: 'Retrieve Policy',
    description: 'GET /access/policies/{id} — get a policy by ID',
    httpMethod: 'GET',
    fields: [
      { name: 'policy_id', label: 'Policy ID', type: 'text', required: true, placeholder: 'pol_...' },
    ],
  },
  {
    action: 'policies.update',
    label: 'Update Policy',
    description: 'PATCH /access/policies/{id} — update policy fields',
    httpMethod: 'PATCH',
    fields: [
      { name: 'policy_id', label: 'Policy ID', type: 'text', required: true, placeholder: 'pol_...' },
      { name: 'name', label: 'Name', type: 'text' },
      { name: 'description', label: 'Description', type: 'text' },
      { name: 'source_ids', label: 'Source IDs (JSON array)', type: 'textarea', placeholder: '["src_abc123"]' },
      { name: 'row_filters', label: 'Row Filters (JSON array)', type: 'textarea', placeholder: '[{"column":"region","values":["US"]}]' },
    ],
  },
  {
    action: 'policies.del',
    label: 'Delete Policy',
    description: 'DELETE /access/policies/{id} — permanently delete a policy',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'policy_id', label: 'Policy ID', type: 'text', required: true, placeholder: 'pol_...' },
    ],
  },
  {
    action: 'policies.assignUsers',
    label: 'Assign Users to Policy',
    description: 'POST /access/policies/{id}/users — assign users to a policy',
    httpMethod: 'POST',
    fields: [
      { name: 'policy_id', label: 'Policy ID', type: 'text', required: true, placeholder: 'pol_...' },
      { name: 'user_ids', label: 'User IDs (comma-separated)', type: 'text', required: true, placeholder: 'user_01H..., user_02H...' },
    ],
  },
  {
    action: 'policies.removeUser',
    label: 'Remove User from Policy',
    description: 'DELETE /access/policies/{id}/users/{userId} — remove a user from a policy',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'policy_id', label: 'Policy ID', type: 'text', required: true, placeholder: 'pol_...' },
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'policies.resolve',
    label: 'Resolve Access',
    description: 'POST /access/resolve — resolve effective filters for a user + source',
    httpMethod: 'POST',
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
      { name: 'source_id', label: 'Source ID', type: 'text', required: true, placeholder: 'src_...' },
    ],
  },
  {
    action: 'policies.columns',
    label: 'List Columns',
    description: 'GET /access/columns — list available columns for row-level filtering',
    httpMethod: 'GET',
    fields: [
      { name: 'source_id', label: 'Source ID (optional)', type: 'text', placeholder: 'src_...' },
    ],
  },
];

const dashboardsMethods: MethodDef[] = [
  {
    action: 'dashboards.list',
    label: 'List Dashboards',
    description: 'GET /dashboards — list all dashboards',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'dashboards.create',
    label: 'Create Dashboard',
    description: 'POST /dashboards — create a new dashboard',
    httpMethod: 'POST',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Sales Overview' },
      { name: 'description', label: 'Description', type: 'text', placeholder: 'Monthly sales metrics' },
    ],
  },
  {
    action: 'dashboards.retrieve',
    label: 'Retrieve Dashboard',
    description: 'GET /dashboards/{id} — get dashboard with widgets and filters',
    httpMethod: 'GET',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
    ],
  },
  {
    action: 'dashboards.update',
    label: 'Update Dashboard',
    description: 'PATCH /dashboards/{id} — update name or description',
    httpMethod: 'PATCH',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
      { name: 'name', label: 'Name', type: 'text' },
      { name: 'description', label: 'Description', type: 'text' },
    ],
  },
  {
    action: 'dashboards.del',
    label: 'Delete Dashboard',
    description: 'DELETE /dashboards/{id} — permanently delete a dashboard',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
    ],
  },
  {
    action: 'dashboards.refresh',
    label: 'Refresh Dashboard',
    description: 'POST /dashboards/{id}/refresh — trigger data refresh',
    httpMethod: 'POST',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
    ],
  },
  {
    action: 'dashboards.refreshStatus',
    label: 'Refresh Status',
    description: 'GET /dashboards/{id}/refresh/status — check refresh progress',
    httpMethod: 'GET',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
    ],
  },
];

const projectsMethods: MethodDef[] = [
  {
    action: 'projects.list',
    label: 'List Projects',
    description: 'GET /projects — cursor-paginated project list',
    httpMethod: 'GET',
    fields: [
      { name: 'limit', label: 'Limit', type: 'number', placeholder: '50' },
      { name: 'after', label: 'After (cursor)', type: 'text' },
    ],
  },
  {
    action: 'projects.create',
    label: 'Create Project',
    description: 'POST /projects — create a new analysis project',
    httpMethod: 'POST',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Q1 Analysis' },
      { name: 'description', label: 'Description', type: 'text' },
      { name: 'user_id', label: 'User ID (owner)', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'projects.retrieve',
    label: 'Retrieve Project',
    description: 'GET /projects/{id} — get project with steps',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'projects.update',
    label: 'Update Project',
    description: 'PUT /projects/{id} — update name or description',
    httpMethod: 'PUT',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'name', label: 'Name', type: 'text' },
      { name: 'description', label: 'Description', type: 'text' },
    ],
  },
  {
    action: 'projects.del',
    label: 'Delete Project',
    description: 'DELETE /projects/{id} — permanently delete a project',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'projects.run',
    label: 'Run Project',
    description: 'POST /projects/{id}/run — submit project for execution',
    httpMethod: 'POST',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
    ],
  },
  {
    action: 'projects.runStatus',
    label: 'Run Status',
    description: 'GET /projects/{id}/run/status — check execution status',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'projects.runCancel',
    label: 'Cancel Run',
    description: 'POST /projects/{id}/run/cancel — cancel running execution',
    httpMethod: 'POST',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'projects.listSteps',
    label: 'List Steps',
    description: 'GET /projects/{id}/steps — list analysis steps',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'projects.getStepData',
    label: 'Get Step Data',
    description: 'GET /projects/{id}/steps/{stepId}/data — paginated step results',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'step_id', label: 'Step ID', type: 'text', required: true },
      { name: 'page', label: 'Page', type: 'number', placeholder: '1' },
      { name: 'page_size', label: 'Page Size', type: 'number', placeholder: '100' },
    ],
  },
];

const chatsMethods: MethodDef[] = [
  {
    action: 'chats.create',
    label: 'Create Chat',
    description: 'POST /projects/{id}/chats — create a chat in a project',
    httpMethod: 'POST',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'name', label: 'Chat Name', type: 'text', placeholder: 'Analysis discussion' },
    ],
  },
  {
    action: 'chats.list',
    label: 'List Chats',
    description: 'GET /projects/{id}/chats — list chats in a project',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'chats.retrieve',
    label: 'Retrieve Chat',
    description: 'GET /projects/{id}/chats/{chatId} — get chat with messages',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'chat_id', label: 'Chat ID', type: 'text', required: true },
    ],
  },
  {
    action: 'chats.del',
    label: 'Delete Chat',
    description: 'DELETE /projects/{id}/chats/{chatId} — delete a chat',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'chat_id', label: 'Chat ID', type: 'text', required: true },
    ],
  },
  {
    action: 'chats.cancel',
    label: 'Cancel Stream',
    description: 'POST /projects/{id}/chats/{chatId}/cancel — cancel active stream',
    httpMethod: 'POST',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'chat_id', label: 'Chat ID', type: 'text', required: true },
    ],
  },
];

const dataMethods: MethodDef[] = [
  {
    action: 'data.listSources',
    label: 'List Data Sources',
    description: 'GET /data/sources — list available data sources',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'data.getSource',
    label: 'Get Source',
    description: 'GET /data/sources/{id} — get source metadata and schema',
    httpMethod: 'GET',
    fields: [
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
    ],
  },
  {
    action: 'data.query',
    label: 'Execute Query',
    description: 'POST /data/query — run SQL with automatic RLS enforcement',
    httpMethod: 'POST',
    fields: [
      { name: 'sql', label: 'SQL', type: 'textarea', required: true, placeholder: 'SELECT * FROM table LIMIT 10' },
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
      { name: 'page', label: 'Page', type: 'number', placeholder: '1' },
      { name: 'page_size', label: 'Page Size', type: 'number', placeholder: '100' },
    ],
  },
  {
    action: 'data.getSourceData',
    label: 'Get Source Data',
    description: 'GET /data/sources/{id}/data — paginated source data with RLS',
    httpMethod: 'GET',
    fields: [
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
      { name: 'page', label: 'Page', type: 'number', placeholder: '1' },
      { name: 'page_size', label: 'Page Size', type: 'number', placeholder: '100' },
    ],
  },
];

const sourcesMethods: MethodDef[] = [
  {
    action: 'sources.listConnectors',
    label: 'List Connectors',
    description: 'GET /connectors — list available connector types',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'sources.list',
    label: 'List Sources',
    description: 'GET /sources — list configured data sources',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'sources.create',
    label: 'Create Source',
    description: 'POST /sources — create a new data source',
    httpMethod: 'POST',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Sales DB' },
      { name: 'connector_id', label: 'Connector ID', type: 'text', required: true },
      { name: 'config', label: 'Config (JSON)', type: 'textarea', required: true, placeholder: '{"host":"...","database":"..."}' },
    ],
  },
  {
    action: 'sources.update',
    label: 'Update Source',
    description: 'PATCH /sources/{id} — update source name or config',
    httpMethod: 'PATCH',
    fields: [
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
      { name: 'name', label: 'Name', type: 'text' },
      { name: 'config', label: 'Config (JSON)', type: 'textarea', placeholder: '{"host":"..."}' },
    ],
  },
  {
    action: 'sources.del',
    label: 'Delete Source',
    description: 'DELETE /sources/{id} — delete a data source',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
    ],
  },
  {
    action: 'sources.sync',
    label: 'Sync Source',
    description: 'POST /sources/{id}/sync — trigger source data sync',
    httpMethod: 'POST',
    fields: [
      { name: 'source_id', label: 'Source ID', type: 'text', required: true },
    ],
  },
];

const filesMethods: MethodDef[] = [
  {
    action: 'files.list',
    label: 'List Files',
    description: 'GET /files — list uploaded files',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'files.retrieve',
    label: 'Retrieve File',
    description: 'GET /files/{id} — get file metadata',
    httpMethod: 'GET',
    fields: [
      { name: 'file_id', label: 'File ID', type: 'text', required: true },
    ],
  },
  {
    action: 'files.del',
    label: 'Delete File',
    description: 'DELETE /files/{id} — delete a file',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'file_id', label: 'File ID', type: 'text', required: true },
    ],
  },
];

const keysMethods: MethodDef[] = [
  {
    action: 'keys.create',
    label: 'Create API Key',
    description: 'POST /keys — create a new API key (secret shown once)',
    httpMethod: 'POST',
    fields: [
      { name: 'name', label: 'Key Name', type: 'text', required: true, placeholder: 'Production Key' },
      { name: 'scopes', label: 'Scopes (JSON array)', type: 'textarea', required: true, placeholder: '["data:read","admin:users:read"]' },
      { name: 'expires_in_days', label: 'Expires In (days)', type: 'number', placeholder: '90' },
      { name: 'rate_limit_per_minute', label: 'Rate Limit/min', type: 'number', placeholder: '60' },
    ],
  },
  {
    action: 'keys.list',
    label: 'List API Keys',
    description: 'GET /keys — list all API keys (no secrets)',
    httpMethod: 'GET',
    fields: [],
  },
  {
    action: 'keys.retrieve',
    label: 'Retrieve API Key',
    description: 'GET /keys/{id} — get key details',
    httpMethod: 'GET',
    fields: [
      { name: 'key_id', label: 'Key ID', type: 'text', required: true },
    ],
  },
  {
    action: 'keys.revoke',
    label: 'Revoke API Key',
    description: 'DELETE /keys/{id} — revoke an API key',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'key_id', label: 'Key ID', type: 'text', required: true },
    ],
  },
];

const auditMethods: MethodDef[] = [
  {
    action: 'audit.listEvents',
    label: 'List Audit Events',
    description: 'GET /audit/events — query audit log with filters',
    httpMethod: 'GET',
    fields: [
      { name: 'actor_id', label: 'Actor ID', type: 'text' },
      { name: 'target_id', label: 'Target ID', type: 'text' },
      { name: 'action', label: 'Action', type: 'text', placeholder: 'project.create' },
      { name: 'start_date', label: 'Start Date', type: 'text', placeholder: '2025-01-01' },
      { name: 'end_date', label: 'End Date', type: 'text', placeholder: '2025-12-31' },
      { name: 'page', label: 'Page', type: 'number', placeholder: '1' },
      { name: 'page_size', label: 'Page Size', type: 'number', placeholder: '50' },
    ],
  },
];

const usageMethods: MethodDef[] = [
  {
    action: 'usage.org',
    label: 'Organization Usage',
    description: 'GET /usage — org-level usage summary',
    httpMethod: 'GET',
    fields: [
      { name: 'period', label: 'Period', type: 'text', placeholder: 'current_month', defaultValue: 'current_month' },
    ],
  },
  {
    action: 'usage.user',
    label: 'User Usage',
    description: 'GET /usage/users/{id} — per-user usage breakdown',
    httpMethod: 'GET',
    fields: [
      { name: 'user_id', label: 'User ID', type: 'text', required: true, placeholder: 'user_01H...' },
      { name: 'period', label: 'Period', type: 'text', placeholder: 'current_month', defaultValue: 'current_month' },
    ],
  },
];

const sharingMethods: MethodDef[] = [
  {
    action: 'sharing.shareProject',
    label: 'Share Project',
    description: 'POST /projects/{id}/shares — grant user access',
    httpMethod: 'POST',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'user_id', label: 'User ID', type: 'text', required: true },
      { name: 'permission', label: 'Permission', type: 'text', placeholder: 'view', defaultValue: 'view' },
    ],
  },
  {
    action: 'sharing.revokeProjectShare',
    label: 'Revoke Project Share',
    description: 'DELETE /projects/{id}/shares/{userId} — revoke access',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
      { name: 'user_id', label: 'User ID', type: 'text', required: true },
    ],
  },
  {
    action: 'sharing.listProjectShares',
    label: 'List Project Shares',
    description: 'GET /projects/{id}/shares — list who has access',
    httpMethod: 'GET',
    fields: [
      { name: 'project_id', label: 'Project ID', type: 'text', required: true },
    ],
  },
  {
    action: 'sharing.shareDashboard',
    label: 'Share Dashboard',
    description: 'POST /dashboards/{id}/shares — grant user access',
    httpMethod: 'POST',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
      { name: 'user_id', label: 'User ID', type: 'text', required: true },
      { name: 'permission', label: 'Permission', type: 'text', placeholder: 'view', defaultValue: 'view' },
    ],
  },
  {
    action: 'sharing.revokeDashboardShare',
    label: 'Revoke Dashboard Share',
    description: 'DELETE /dashboards/{id}/shares/{userId} — revoke access',
    httpMethod: 'DELETE',
    dangerous: true,
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
      { name: 'user_id', label: 'User ID', type: 'text', required: true },
    ],
  },
  {
    action: 'sharing.listDashboardShares',
    label: 'List Dashboard Shares',
    description: 'GET /dashboards/{id}/shares — list who has access',
    httpMethod: 'GET',
    fields: [
      { name: 'dashboard_id', label: 'Dashboard ID', type: 'text', required: true },
    ],
  },
];

const tabs = [
  { key: 'users', label: 'Users', methods: usersMethods },
  { key: 'embed', label: 'Embed', methods: embedMethods },
  { key: 'policies', label: 'Policies', methods: policiesMethods },
  { key: 'dashboards', label: 'Dashboards', methods: dashboardsMethods },
  { key: 'projects', label: 'Projects', methods: projectsMethods },
  { key: 'chats', label: 'Chats', methods: chatsMethods },
  { key: 'data', label: 'Data', methods: dataMethods },
  { key: 'sources', label: 'Sources', methods: sourcesMethods },
  { key: 'files', label: 'Files', methods: filesMethods },
  { key: 'keys', label: 'API Keys', methods: keysMethods },
  { key: 'audit', label: 'Audit', methods: auditMethods },
  { key: 'usage', label: 'Usage', methods: usageMethods },
  { key: 'sharing', label: 'Sharing', methods: sharingMethods },
] as const;

type TabKey = (typeof tabs)[number]['key'];

const totalMethods = tabs.reduce((sum, t) => sum + t.methods.length, 0);

// ─── Styles ──────────────────────────────────────────────

const pageStyle: React.CSSProperties = {
  padding: '1.5rem 2rem',
  fontFamily: 'system-ui, sans-serif',
  maxWidth: '1200px',
  margin: '0 auto',
};

const tabBarStyle: React.CSSProperties = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: '0.4rem',
  marginBottom: '1.5rem',
};

const tabStyle = (active: boolean): React.CSSProperties => ({
  padding: '0.4rem 0.75rem',
  borderRadius: '20px',
  border: `1px solid ${active ? '#0066cc' : '#ddd'}`,
  background: active ? '#0066cc' : 'transparent',
  color: active ? '#fff' : '#555',
  fontSize: '0.8rem',
  fontWeight: 600,
  cursor: 'pointer',
  fontFamily: 'system-ui, sans-serif',
});

const gridStyle: React.CSSProperties = {
  display: 'grid',
  gridTemplateColumns: '1fr 1fr',
  gap: '1.5rem',
  alignItems: 'start',
};

// ─── Component ───────────────────────────────────────────

export default function ExplorerPage() {
  const [activeTab, setActiveTab] = useState<TabKey>('users');
  const [response, setResponse] = useState<{
    action: string;
    data: unknown;
    error?: string;
    timestamp: string;
  } | null>(null);

  const handleResponse = (action: string, data: unknown, error?: string) => {
    setResponse({
      action,
      data,
      error,
      timestamp: new Date().toLocaleTimeString(),
    });
  };

  const activeGroup = tabs.find((t) => t.key === activeTab)!;

  return (
    <div style={pageStyle}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.25rem' }}>SDK Explorer</h1>
      <p style={{ color: '#666', fontSize: '0.9rem', marginTop: 0, marginBottom: '1.25rem' }}>
        Interactively test all {totalMethods} PHP SDK methods across {tabs.length} resources.
        Select a tab, fill in parameters, and click Run.
      </p>

      <div style={tabBarStyle}>
        {tabs.map((tab) => (
          <button
            key={tab.key}
            style={tabStyle(activeTab === tab.key)}
            onClick={() => setActiveTab(tab.key)}
          >
            {tab.label} ({tab.methods.length})
          </button>
        ))}
      </div>

      <div style={gridStyle}>
        <div>
          {activeGroup.methods.map((method) => (
            <MethodCard
              key={method.action}
              method={method}
              onResponse={handleResponse}
            />
          ))}
        </div>
        <div>
          <JsonViewer
            action={response?.action ?? null}
            data={response?.data}
            error={response?.error}
            timestamp={response?.timestamp}
          />
        </div>
      </div>
    </div>
  );
}

import { useState } from 'react';
import JsonViewer from '../components/JsonViewer';

const pageStyle: React.CSSProperties = {
  padding: '1.5rem 2rem',
  fontFamily: 'system-ui, sans-serif',
  maxWidth: '900px',
  margin: '0 auto',
};

const cardStyle: React.CSSProperties = {
  background: '#fff',
  border: '1px solid #e0e0e0',
  borderRadius: '8px',
  padding: '1.25rem 1.5rem',
  marginBottom: '1.5rem',
};

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: '0.4rem 0.5rem',
  border: '1px solid #ccc',
  borderRadius: '4px',
  fontSize: '0.85rem',
  fontFamily: 'system-ui, sans-serif',
  boxSizing: 'border-box',
};

const labelStyle: React.CSSProperties = {
  fontSize: '0.75rem',
  fontWeight: 600,
  color: '#555',
  marginBottom: '0.2rem',
  display: 'block',
};

const buttonStyle = (loading: boolean): React.CSSProperties => ({
  background: loading ? '#999' : '#0066cc',
  color: '#fff',
  border: 'none',
  padding: '0.5rem 1.5rem',
  borderRadius: '4px',
  fontSize: '0.85rem',
  fontWeight: 600,
  cursor: loading ? 'not-allowed' : 'pointer',
  marginTop: '0.5rem',
});

const gridStyle: React.CSSProperties = {
  display: 'grid',
  gridTemplateColumns: '1fr 1fr',
  gap: '1.5rem',
  alignItems: 'start',
};

export default function UserProjectsPage() {
  const [externalId, setExternalId] = useState('');
  const [email, setEmail] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [loading, setLoading] = useState(false);
  const [response, setResponse] = useState<{
    action: string;
    data: unknown;
    error?: string;
    timestamp: string;
  } | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const body: Record<string, string> = { external_id: externalId };
      if (email) body.email = email;
      if (firstName) body.first_name = firstName;
      if (lastName) body.last_name = lastName;

      const res = await fetch('/api/user-projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      const json = await res.json();

      if (!res.ok) {
        setResponse({
          action: 'user-projects',
          data: null,
          error: json.error || `HTTP ${res.status}`,
          timestamp: new Date().toLocaleTimeString(),
        });
      } else {
        setResponse({
          action: 'user-projects',
          data: json,
          timestamp: new Date().toLocaleTimeString(),
        });
      }
    } catch (err) {
      setResponse({
        action: 'user-projects',
        data: null,
        error: err instanceof Error ? err.message : 'Unknown error',
        timestamp: new Date().toLocaleTimeString(),
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={pageStyle}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.25rem' }}>User Projects</h1>
      <p style={{ color: '#666', fontSize: '0.9rem', marginTop: 0, marginBottom: '1.25rem' }}>
        List only the projects a specific embed user has access to via FGA permissions.
        Uses <code>$client-&gt;asUser($session)</code> to call the internal API with session auth.
      </p>

      <div style={gridStyle}>
        <form style={cardStyle} onSubmit={handleSubmit}>
          <h3 style={{ margin: '0 0 0.75rem', fontSize: '1rem' }}>Look up user projects</h3>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '0.75rem' }}>
            <div>
              <label style={labelStyle}>
                External ID <span style={{ color: '#dc3545' }}>*</span>
              </label>
              <input
                style={inputStyle}
                type="text"
                placeholder="my-user-123"
                value={externalId}
                onChange={(e) => setExternalId(e.target.value)}
                required
              />
            </div>
            <div>
              <label style={labelStyle}>Email</label>
              <input
                style={inputStyle}
                type="text"
                placeholder="user@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div>
              <label style={labelStyle}>First Name</label>
              <input
                style={inputStyle}
                type="text"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
              />
            </div>
            <div>
              <label style={labelStyle}>Last Name</label>
              <input
                style={inputStyle}
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
              />
            </div>
          </div>

          <button type="submit" disabled={loading} style={buttonStyle(loading)}>
            {loading ? 'Loading...' : 'Get Projects'}
          </button>

          <div style={{ marginTop: '1rem', padding: '0.75rem', background: '#f8f9fa', borderRadius: '6px', fontSize: '0.8rem', color: '#555' }}>
            <strong>How it works:</strong>
            <ol style={{ margin: '0.5rem 0 0', paddingLeft: '1.25rem' }}>
              <li>Resolves the user via <code>getSession()</code> (getOrCreate + embed session)</li>
              <li>Creates a user client via <code>$client-&gt;asUser($session)</code></li>
              <li>Calls <code>$userClient-&gt;projects-&gt;list()</code> — internal API applies FGA filtering</li>
            </ol>
            <p style={{ margin: '0.5rem 0 0' }}>
              To grant a user access to a project, use <code>sharing.shareProject()</code> in the SDK Explorer.
            </p>
          </div>
        </form>

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

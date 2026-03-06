import { useState } from 'react';

export interface FieldDef {
  name: string;
  label: string;
  type: 'text' | 'number' | 'textarea';
  required?: boolean;
  placeholder?: string;
  defaultValue?: string;
}

export interface MethodDef {
  action: string;
  label: string;
  description: string;
  httpMethod: string;
  fields: FieldDef[];
  dangerous?: boolean;
}

interface MethodCardProps {
  method: MethodDef;
  onResponse: (action: string, data: unknown, error?: string) => void;
}

const TOP_LEVEL_KEYS = new Set([
  'user_id', 'policy_id', 'external_id', 'session_id',
  'session_token', 'source_id', 'limit', 'after', 'name',
  'user_ids',
]);

const badgeColors: Record<string, string> = {
  GET: '#28a745',
  POST: '#0066cc',
  PUT: '#e68a00',
  PATCH: '#d4a017',
  DELETE: '#dc3545',
};

const cardStyle: React.CSSProperties = {
  background: '#fff',
  border: '1px solid #e0e0e0',
  borderRadius: '8px',
  padding: '1rem 1.25rem',
  marginBottom: '0.75rem',
};

const badgeStyle = (method: string): React.CSSProperties => ({
  display: 'inline-block',
  background: badgeColors[method] || '#666',
  color: '#fff',
  padding: '2px 8px',
  borderRadius: '4px',
  fontSize: '0.7rem',
  fontWeight: 700,
  fontFamily: 'monospace',
  marginRight: '0.5rem',
  verticalAlign: 'middle',
});

const labelStyle: React.CSSProperties = {
  fontSize: '0.95rem',
  fontWeight: 600,
  color: '#333',
};

const descStyle: React.CSSProperties = {
  fontSize: '0.8rem',
  color: '#777',
  margin: '0.25rem 0 0.75rem',
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

const fieldLabelStyle: React.CSSProperties = {
  fontSize: '0.75rem',
  fontWeight: 600,
  color: '#555',
  marginBottom: '0.2rem',
  display: 'block',
};

const buttonStyle = (dangerous: boolean, loading: boolean): React.CSSProperties => ({
  background: loading ? '#999' : dangerous ? '#dc3545' : '#0066cc',
  color: '#fff',
  border: 'none',
  padding: '0.45rem 1.25rem',
  borderRadius: '4px',
  fontSize: '0.85rem',
  fontWeight: 600,
  cursor: loading ? 'not-allowed' : 'pointer',
  marginTop: '0.25rem',
});

export default function MethodCard({ method, onResponse }: MethodCardProps) {
  const [values, setValues] = useState<Record<string, string>>(() => {
    const init: Record<string, string> = {};
    for (const f of method.fields) {
      init[f.name] = f.defaultValue || '';
    }
    return init;
  });
  const [loading, setLoading] = useState(false);

  const handleChange = (name: string, value: string) => {
    setValues((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      // Build params: top-level keys stay at root, others nest under 'data'
      const params: Record<string, unknown> = {};
      const data: Record<string, unknown> = {};
      let hasData = false;

      for (const field of method.fields) {
        const raw = values[field.name]?.trim();
        if (!raw) continue;

        let parsed: unknown = raw;
        if (field.type === 'number') {
          parsed = parseInt(raw, 10);
          if (isNaN(parsed as number)) continue;
        }
        if (field.type === 'textarea') {
          try {
            parsed = JSON.parse(raw);
          } catch {
            // Keep as string if not valid JSON
          }
        }
        // user_ids special case: comma-separated → array
        if (field.name === 'user_ids' && typeof parsed === 'string') {
          parsed = parsed.split(',').map((s) => s.trim()).filter(Boolean);
        }

        if (TOP_LEVEL_KEYS.has(field.name)) {
          params[field.name] = parsed;
        } else {
          data[field.name] = parsed;
          hasData = true;
        }
      }

      // For update actions, nest non-top-level fields under 'data'
      if (hasData && method.action.includes('update')) {
        params.data = data;
      } else if (hasData) {
        // For create actions, merge into params directly
        Object.assign(params, data);
      }

      const res = await fetch('/api/sdk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: method.action, params }),
      });

      const json = await res.json();

      if (!res.ok) {
        onResponse(method.action, null, json.error || `HTTP ${res.status}`);
      } else {
        onResponse(method.action, json);
      }
    } catch (err) {
      onResponse(method.action, null, err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form style={cardStyle} onSubmit={handleSubmit}>
      <div>
        <span style={badgeStyle(method.httpMethod)}>{method.httpMethod}</span>
        <span style={labelStyle}>{method.label}</span>
      </div>
      <div style={descStyle}>{method.description}</div>

      {method.fields.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '0.75rem' }}>
          {method.fields.map((field) => (
            <div key={field.name}>
              <label style={fieldLabelStyle}>
                {field.label}
                {field.required && <span style={{ color: '#dc3545' }}> *</span>}
              </label>
              {field.type === 'textarea' ? (
                <textarea
                  style={{ ...inputStyle, minHeight: '60px', resize: 'vertical' }}
                  placeholder={field.placeholder}
                  value={values[field.name] || ''}
                  onChange={(e) => handleChange(field.name, e.target.value)}
                  required={field.required}
                />
              ) : (
                <input
                  style={inputStyle}
                  type={field.type === 'number' ? 'number' : 'text'}
                  placeholder={field.placeholder}
                  value={values[field.name] || ''}
                  onChange={(e) => handleChange(field.name, e.target.value)}
                  required={field.required}
                />
              )}
            </div>
          ))}
        </div>
      )}

      <button
        type="submit"
        disabled={loading}
        style={buttonStyle(!!method.dangerous, loading)}
      >
        {loading ? 'Running...' : method.dangerous ? 'Delete' : 'Run'}
      </button>
    </form>
  );
}

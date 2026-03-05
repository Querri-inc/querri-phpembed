interface JsonViewerProps {
  action: string | null;
  data: unknown;
  error?: string | null;
  timestamp?: string | null;
}

const containerStyle: React.CSSProperties = {
  position: 'sticky',
  top: '1rem',
  fontFamily: 'system-ui, sans-serif',
};

const headerStyle: React.CSSProperties = {
  fontSize: '0.85rem',
  fontWeight: 600,
  color: '#555',
  marginBottom: '0.5rem',
};

const preStyle = (isError: boolean): React.CSSProperties => ({
  backgroundColor: isError ? '#fff5f5' : '#f8f9fa',
  border: `1px solid ${isError ? '#f5c6cb' : '#e0e0e0'}`,
  borderRadius: '8px',
  padding: '1rem',
  fontSize: '0.8rem',
  fontFamily: "'SF Mono', 'Fira Code', 'Cascadia Code', monospace",
  lineHeight: 1.5,
  overflow: 'auto',
  maxHeight: '70vh',
  whiteSpace: 'pre-wrap',
  wordBreak: 'break-word',
  color: isError ? '#721c24' : '#333',
  margin: 0,
});

const emptyStyle: React.CSSProperties = {
  color: '#999',
  fontSize: '0.85rem',
  textAlign: 'center',
  padding: '3rem 1rem',
  border: '1px dashed #ddd',
  borderRadius: '8px',
};

export default function JsonViewer({ action, data, error, timestamp }: JsonViewerProps) {
  if (!action) {
    return (
      <div style={containerStyle}>
        <div style={headerStyle}>Response</div>
        <div style={emptyStyle}>
          Run a method to see the response here
        </div>
      </div>
    );
  }

  const formatted = error
    ? JSON.stringify({ error }, null, 2)
    : JSON.stringify(data, null, 2);

  return (
    <div style={containerStyle}>
      <div style={headerStyle}>
        {action}
        {timestamp && (
          <span style={{ fontWeight: 400, color: '#999', marginLeft: '0.75rem' }}>
            {timestamp}
          </span>
        )}
      </div>
      <pre style={preStyle(!!error)}>{formatted}</pre>
    </div>
  );
}

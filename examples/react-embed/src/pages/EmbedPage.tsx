import { QuerriEmbed } from '@querri-inc/embed/react';
import { useMemo } from 'react';

export default function EmbedPage() {
  const auth = useMemo(
    () => ({
      sessionEndpoint: '/api/querri-session.php',
    }),
    [],
  );

  return (
    <main style={{ padding: '2rem', fontFamily: 'system-ui, sans-serif' }}>
      <h1>Querri Embed - PHP + React Example</h1>
      <p>
        This example uses a PHP backend with the{' '}
        <a href="https://github.com/Querri-inc/querri-phpembed">
          Querri PHP SDK
        </a>{' '}
        and a React frontend. The PHP server creates embed sessions via{' '}
        <code>QuerriClient::getSession()</code>.
      </p>
      <QuerriEmbed
        style={{ width: '100%', height: '80vh', marginTop: '1rem' }}
        serverUrl={import.meta.env.VITE_QUERRI_URL || 'https://app.querri.com'}
        auth={auth}
        chrome={{ sidebar: { show: true } }}
        onReady={() => console.log('Embed ready')}
        onError={(err) => console.error('Embed error:', err)}
      />
    </main>
  );
}

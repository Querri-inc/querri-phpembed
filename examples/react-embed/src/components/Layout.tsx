import { NavLink, Outlet } from 'react-router-dom';

const navStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: '1.5rem',
  padding: '0.75rem 2rem',
  borderBottom: '1px solid #e0e0e0',
  fontFamily: 'system-ui, sans-serif',
  backgroundColor: '#fff',
};

const titleStyle: React.CSSProperties = {
  fontSize: '1.1rem',
  fontWeight: 700,
  color: '#333',
  marginRight: '1rem',
};

const linkStyle = (isActive: boolean): React.CSSProperties => ({
  textDecoration: 'none',
  padding: '0.4rem 0.8rem',
  borderRadius: '6px',
  fontSize: '0.9rem',
  fontWeight: 500,
  color: isActive ? '#fff' : '#555',
  backgroundColor: isActive ? '#0066cc' : 'transparent',
  transition: 'background-color 0.15s',
});

export default function Layout() {
  return (
    <>
      <nav style={navStyle}>
        <span style={titleStyle}>Querri PHP SDK</span>
        <NavLink to="/" end style={({ isActive }) => linkStyle(isActive)}>
          Embed Demo
        </NavLink>
        <NavLink to="/explorer" style={({ isActive }) => linkStyle(isActive)}>
          SDK Explorer
        </NavLink>
        <NavLink to="/user-projects" style={({ isActive }) => linkStyle(isActive)}>
          User Projects
        </NavLink>
      </nav>
      <Outlet />
    </>
  );
}

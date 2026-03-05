import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import EmbedPage from './pages/EmbedPage';
import ExplorerPage from './pages/ExplorerPage';
import UserProjectsPage from './pages/UserProjectsPage';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <Routes>
        <Route element={<Layout />}>
          <Route index element={<EmbedPage />} />
          <Route path="explorer" element={<ExplorerPage />} />
          <Route path="user-projects" element={<UserProjectsPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  </StrictMode>,
);

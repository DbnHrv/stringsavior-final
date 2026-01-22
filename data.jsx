import { createRoot } from 'stringsavior-final/react';

function Hello() {
  return (
    <h1>Hello World!</h1>
  );
}

createRoot(document.getElementById('root')).render(
  <Hello />
);
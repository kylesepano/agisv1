export default function AuthLoadingScreen() {
  return (
    <main className="grid min-h-screen place-items-center bg-[linear-gradient(rgba(4,68,126,.82),rgba(4,42,91,.9)),url('/loginbackground.jpg')] bg-cover bg-center p-6 font-['Segoe_UI',Arial,sans-serif]">
      <div
        className="grid w-full max-w-sm justify-items-center gap-4 rounded-xl border border-white/60 bg-[#07518d]/90 p-9 text-center text-white shadow-2xl backdrop-blur-md"
        role="status"
        aria-live="polite"
      >
        <img className="h-24 w-24 object-contain" src="/logo.png" alt="" />
        <span className="h-7 w-7 animate-spin rounded-full border-2 border-white/40 border-t-white" />
        <strong className="text-xl">Loading AGIS</strong>
        <p className="text-sm text-blue-100">Checking your secure session...</p>
      </div>
    </main>
  );
}

import { ImageResponse } from "next/og";

// Official BFS Circle Monochrome Favicon
// Next.js renders this at build time and auto-injects <link rel="icon"> into <head>.

export const runtime = "edge";
export const size = { width: 32, height: 32 };
export const contentType = "image/png";

export default function Icon() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          background: "#09090b",
          borderRadius: 8,
          border: "1px solid rgba(255, 255, 255, 0.25)",
        }}
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
        >
          <circle cx="12" cy="12" r="10" stroke="#ffffff" strokeWidth="2" />
          <path d="M8 16V13" stroke="#ffffff" strokeWidth="2.5" strokeLinecap="round" />
          <path d="M12 16V10" stroke="#ffffff" strokeWidth="2.5" strokeLinecap="round" />
          <path d="M16 16V7" stroke="#ffffff" strokeWidth="2.5" strokeLinecap="round" />
        </svg>
      </div>
    ),
    { ...size },
  );
}

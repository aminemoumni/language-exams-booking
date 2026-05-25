import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Enable standalone output for Docker prod image
  output: 'standalone',
  reactStrictMode: true,
}

export default nextConfig

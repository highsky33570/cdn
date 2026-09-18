// Public region inventory only. Probe targets stay in backend config/network.php.
// Coordinates follow the un-cropped 3933 × 2540 map asset.
export const edgeLocations = [
  {
    key: 'hongkong',
    label: '香港',
    x: 80.5,
    y: 63,
    count: 5,
    online_count: null
  }
]

// Illustrative audience locations, not additional TyCDN nodes or measured routes.
export const audienceLocations = [
  { key: 'los-angeles', x: 17.5, y: 47 },
  { key: 'frankfurt', x: 51, y: 37 },
  { key: 'beijing', x: 80.7, y: 49 },
  { key: 'tokyo', x: 85.6, y: 54.3 },
  { key: 'singapore', x: 78, y: 74 },
  { key: 'sydney', x: 91, y: 87 }
]

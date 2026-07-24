import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { computeHistogram } from '@/lib/histogram';

vi.mock('@/lib/histogram', async (orig) => ({
    ...(await orig()),
    histogramFromUrl: vi.fn().mockResolvedValue({
        r: Array(256).fill(1), g: Array(256).fill(1), b: Array(256).fill(1), luma: Array(256).fill(1),
    }),
}));

import PhotoInfoPanel from '@/Components/Photos/PhotoInfoPanel.vue';

const photo = {
    id: 9,
    name: 'ridge.jpg',
    url: '/raw/9',
    size: 2_400_000,
    width: 6000,
    height: 4000,
    taken_at: 1751900000,
    camera: 'Canon EOS R5',
    photo_meta: {
        exposure: '1/250',
        aperture: 'f/2.8',
        iso: 400,
        focal_length: '50 mm',
        gps: { lat: 40.44, lng: -79.98 },
        iptc: { title: 'Ridge Line', keywords: ['hiking'] },
    },
};

describe('PhotoInfoPanel', () => {
    it('renders details, camera EXIF, description, and a GPS link-out', async () => {
        const wrapper = mount(PhotoInfoPanel, { props: { photo } });
        await flushPromises();
        expect(wrapper.text()).toContain('6000 × 4000');
        expect(wrapper.text()).toContain('2.3 MB');
        expect(wrapper.text()).toContain('Canon EOS R5');
        expect(wrapper.text()).toContain('f/2.8');
        expect(wrapper.text()).toContain('Ridge Line');
        expect(wrapper.text()).toContain('hiking');
        const gps = wrapper.get('[data-testid="photo-info-gps"]');
        expect(gps.attributes('href')).toContain('openstreetmap.org/?mlat=40.44&mlon=-79.98');
    });

    it('hides empty sections and renders the histogram chart when computed', async () => {
        const bare = { ...photo, camera: null, photo_meta: null };
        const wrapper = mount(PhotoInfoPanel, { props: { photo: bare } });
        await flushPromises();
        expect(wrapper.find('[data-testid="photo-info-camera"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="photo-info-description"]').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'VibeChartLine' }).exists()).toBe(true);
    });
});

describe('computeHistogram', () => {
    it('buckets channels and luma, skipping transparent pixels', () => {
        // Two pixels: pure red opaque, pure white transparent.
        const data = new Uint8ClampedArray([255, 0, 0, 255, 255, 255, 255, 0]);
        const h = computeHistogram({ data });
        expect(h.r[255]).toBe(1);
        expect(h.g[0]).toBe(1);
        expect(h.b[0]).toBe(1);
        // Luma of pure red = round(0.2126*255) = 54.
        expect(h.luma[54]).toBe(1);
        // Transparent pixel contributed nothing.
        expect(h.r.reduce((a, b) => a + b, 0)).toBe(1);
    });
});

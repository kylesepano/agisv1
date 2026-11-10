import { Plus } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Heading, Paragraph } from "../../../../components/ui/typography";

type CategoriesHeaderProps = {
  onAddCategory?: () => void;
};

export function CategoriesHeader({ onAddCategory }: CategoriesHeaderProps) {
  return (
    <header className="flex items-start justify-between gap-3 max-[700px]:flex-col">
      <div>
        <Heading as={2} className="text-slate-950">
          Categories
        </Heading>
        <Paragraph className="mt-0.5 text-slate-600">
          Organize audit universe classifications and maintain category
          definitions.
        </Paragraph>
      </div>

      <Button className="h-9 rounded-lg px-3.5" onClick={onAddCategory}>
        <Plus size={14} />
        Add Category
      </Button>
    </header>
  );
}

# encoding: UTF-8

module OpenBudget
  class Node
    attr_accessor :id, :name, :children, :parent
    attr_reader :gross_cost, :revenue

    def initialize
      @gross_cost = BalanceCollection.new
      @revenue = BalanceCollection.new
      @children = []
    end

    def touch
      @dirty = true
      if parent
        parent.touch
      end
    end

    def name=(value)
      if @name.present? && @name.downcase != value.downcase
        warn "name diff detected #{value} for #{@name} (#{id})"
      end
      @name = value
    end

    def prepare
      if @dirty
        puts "cleaning #{id}"
        if @children.empty?
          puts "leaf node was dirty"
        else
          sum_gross_cost = BalanceCollection.new
          sum_revenue = BalanceCollection.new
          children.each do |child|
            child.prepare
            sum_gross_cost += child.gross_cost
            sum_revenue += child.revenue
          end
          if sum_gross_cost != gross_cost
            warn "gross_cost diff detected #{id} sum #{sum_gross_cost.accounts.to_s} orig #{gross_cost.accounts.to_s}"
            @gross_cost = sum_gross_cost
          end
          if sum_revenue != revenue
            warn "revenue diff detected #{id} sum #{sum_revenue.accounts.to_s} orig #{revenue.accounts.to_s}"
            @revenue = sum_revenue
          end
        end

        @dirty = false
      end
    end

    def as_json(options = nil)
      json = {
        id: id,
        name: name
      }
      prepare
      json[:gross_cost] = gross_cost unless gross_cost.empty?
      json[:revenue] = revenue unless revenue.empty?
      json[:children] = children unless children.empty?
      json
    end

    def add_gross_cost(type, year, balance)
      if balance < 0
        add_revenue(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        @gross_cost.add(type, year, balance)
      end
    end

    def add_revenue(type, year, balance)
      if balance < 0
        add_gross_cost(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        @revenue.add(type, year, balance)
      end
    end
  end
end
